# Deploying to AWS (EC2 + RDS + S3)

The SFK platform on AWS in Tokyo (`ap-northeast-1`): one EC2 server running the app in Docker, a managed PostgreSQL
database (RDS), uploads in S3 and mail through SES. Deploys run from GitHub Actions with one click. No AWS keys are stored
anywhere: the server and GitHub both get short-lived credentials from IAM roles.

```
            Users ── HTTPS ──►  EC2 t3.small  (Amazon Linux 2023, Docker)                     ap-northeast-1
                                 ├─ caddy   :80/:443  Let's Encrypt certificate, → app:8080
                                 ├─ app     nginx + PHP-FPM + queue worker + scheduler
                                 └─ redis   cache, sessions, queue
                                       │ IAM role (no keys)
             ┌─────────────────────────┼──────────────────────────┐
     RDS PostgreSQL 17           S3 bucket (uploads)          SES (mail)
     private, backups 7 days     private, versioned

  GitHub Actions ── OIDC ──► ECR (images) ──► SSM Run Command ──► /opt/sfk/deploy.sh <commit>
```

Files: `docker/aws/` (server files, IAM policies), `.github/workflows/deploy-aws.yml` (at the repository root),
the production image `docker/render/Dockerfile` (shared with Render).

**Rough cost** (Tokyo, approximate — check the [AWS Pricing Calculator](https://calculator.aws/)): EC2 t3.small
~$20, RDS db.t4g.micro + 20 GB ~$20–25, public IPv4 ~$4, S3 / SES / ECR / data transfer a few dollars — about
**$45–60 a month**. New accounts get Free Tier credits that cover part of the first months.

**Do this first on a staging set-up** with test data. Move the real 受注 / 請求 data only after a backup has been
restored once (step 12) and HTTPS works.

---

## 1. Secure the account (do not skip)

1. Sign in as the **root user** → top right → *Security credentials* → **assign MFA** (an authenticator app).
2. Create your everyday admin: *IAM Identity Center* → enable → *Users* → add yourself → *Permission sets* →
   `AdministratorAccess` → assign to the account. Sign in through the portal URL it shows from now on; use root
   only for billing and account settings.
3. *Billing and Cost Management* → **Budgets** → *Create budget* → monthly cost budget **$60** with e-mail alerts at
   80 % and 100 % (and a forecast alert). This is what keeps a mistake from becoming a big bill.
4. Select the region **Asia Pacific (Tokyo) ap-northeast-1** (top right) and stay in it for every step.

## 2. Security groups (the firewall)

*EC2 → Security Groups → Create* (default VPC):

| Name | Inbound rules | Used by |
|---|---|---|
| `sfk-web` | HTTP 80 and HTTPS 443 from `0.0.0.0/0` and `::/0` | the EC2 server |
| `sfk-db` | PostgreSQL 5432 from the security group `sfk-web` (not an IP) | the RDS database |

No SSH (22) anywhere: you reach the server through **Session Manager** in the browser.

## 3. S3 bucket for uploads

*S3 → Create bucket*: name e.g. `sfk-platform-uploads-<random>`, region Tokyo, **Block all public access: on**
(default), **Bucket Versioning: enable** (a deleted or overwritten file can be brought back), default encryption
SSE-S3. Write the name down for `AWS_BUCKET`.

## 4. RDS PostgreSQL

*RDS → Create database → Standard create*:

- Engine **PostgreSQL 17.x**; template *Dev/Test* (or *Free tier* while learning)
- Identifier `sfk-db`; master username `sfk`; **self-managed** password (a long random one — it goes into `DB_URL`;
  avoid `@ : / ?` in it, or URL-encode them)
- Instance `db.t4g.micro`; storage gp3 20 GB, storage autoscaling on (max 100 GB); single-AZ
- Connectivity: default VPC, **Public access: No**, security group **`sfk-db`** (remove `default`)
- *Additional configuration*: initial database name **`sfk_platform`**; backup retention **7 days**; encryption on;
  **deletion protection on**

When it is *Available*, copy the **Endpoint** (`sfk-db.xxxx.ap-northeast-1.rds.amazonaws.com`). The app's first
migration runs `CREATE EXTENSION pg_trgm` (list search); RDS allows it for the master user.

## 5. IAM role for the server

*IAM → Roles → Create role* → trusted entity **AWS service → EC2**:

- attach `AmazonSSMManagedInstanceCore` (Session Manager, Run Command) and `AmazonEC2ContainerRegistryReadOnly`
  (pull images)
- create the role as **`sfk-ec2-role`**, open it → *Add permissions → Create inline policy → JSON* → paste
  `docker/aws/iam/ec2-app-policy.json` with `BUCKET_NAME` replaced → name it `sfk-app` (S3 uploads + SES mail)

## 6. ECR repository (where images are kept)

*ECR → Create repository*: private, name **`sfk-platform`**, scan on push on. Then *Lifecycle policy* → keep the
last **20** images (older ones are deleted, so storage stays cheap). Copy the URI
(`<account>.dkr.ecr.ap-northeast-1.amazonaws.com/sfk-platform`).

## 7. Launch the EC2 server

*EC2 → Launch instance*:

- Name `sfk-app`; AMI **Amazon Linux 2023** (64-bit **x86**); type **t3.small**
- Key pair: **Proceed without a key pair** (Session Manager instead of SSH)
- Network: default VPC, a public subnet, auto-assign public IP on, security group **`sfk-web`**
- Storage: **30 GB gp3**
- *Advanced details*:
  - IAM instance profile **`sfk-ec2-role`**
  - Metadata version **V2 only**, **Metadata response hop limit: 2** ← required: with 1, the containers cannot get
    the role's credentials and S3 / SES fail with "credentials not found"
  - User data: paste `docker/aws/ec2-user-data.sh` (installs Docker + Compose, swap, `/opt/sfk`)

Then *EC2 → Elastic IPs → Allocate* → *Associate* with `sfk-app`, so the address survives a stop/start. Copy the
**instance ID** (`i-0…`).

Connect: select the instance → *Connect* → **Session Manager** → *Connect*. Check: `docker compose version`.

## 8. Domain and mail

1. At your DNS provider (where the company domain is managed): an **A record** for e.g. `erp.example.co.jp` → the
   Elastic IP. Caddy requests the HTTPS certificate on the first start, so do this before step 10.
2. *SES → Identities → Create identity* → **Domain** `example.co.jp` → add the 3 DKIM CNAME records it shows to DNS
   → wait for *Verified*.
3. A new SES account is in the **sandbox** (mail only to verified addresses, 200 a day): *SES → Account dashboard →
   Request production access* before real use (takes about a day).

## 9. GitHub → AWS without keys (OIDC)

1. *IAM → Identity providers → Add provider* → OpenID Connect, URL `https://token.actions.githubusercontent.com`,
   audience `sts.amazonaws.com`.
2. *IAM → Roles → Create role → Custom trust policy* → paste `docker/aws/iam/github-trust-policy.json` with
   `ACCOUNT_ID` and `GITHUB_OWNER/GITHUB_REPO` replaced (only the `main` branch of this repository may deploy).
   No managed policies; create as **`sfk-github-deploy`**, then add an inline policy from
   `docker/aws/iam/github-deploy-policy.json` (`ACCOUNT_ID`, `INSTANCE_ID` replaced): push to the `sfk-platform`
   repository, run commands on this one server, nothing else.
3. GitHub → repository → *Settings → Secrets and variables → Actions → **Variables*** (not secrets — none of these is
   secret):

| Variable | Value |
|---|---|
| `AWS_DEPLOY_ROLE_ARN` | `arn:aws:iam::<account>:role/sfk-github-deploy` |
| `ECR_REPOSITORY` | `sfk-platform` |
| `EC2_INSTANCE_ID` | `i-0…` |

## 10. Configure the server, then the first deploy

In the Session Manager window:

```sh
sudo -i
cd /opt/sfk

# 1) compose settings: the image (without a tag) and the domain
cat > .env <<'EOF'
ECR_IMAGE=<account>.dkr.ecr.ap-northeast-1.amazonaws.com/sfk-platform
SFK_DOMAIN=erp.example.co.jp
EOF

# 2) the app settings: paste docker/aws/app.env.example with the <…> values filled in, then end with EOF
cat > app.env <<'EOF'
…
EOF
chmod 600 .env app.env
```

`APP_KEY`: run `docker compose exec app php artisan key:generate --show` on your PC and paste the result. Keep it:
changing it later signs everyone out.

Then GitHub → *Actions* → **Deploy to AWS** → *Run workflow* (branch `main`). It builds the image (~5–8 min the first
time, then 1–2), pushes it to ECR and runs `deploy.sh` on the server, which pulls the image, starts Caddy, Redis and
the app, migrates the database and waits until the app answers. Open `https://erp.example.co.jp` and sign in with
`SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD`; then remove `SUPER_ADMIN_PASSWORD` from `app.env` and change the
password in the app.

## 11. Import the legacy data

`data_json/` is never in the image or in git. Copy it through the S3 bucket (private) and delete it afterwards:

```sh
# on your PC (AWS CLI signed in with `aws sso login`)
aws s3 sync sfk-platform/data_json s3://<bucket>/import/data_json

# on the server (Session Manager, sudo -i)
cd /opt/sfk
aws s3 sync s3://<bucket>/import/data_json /opt/sfk/data_json
docker compose run --rm -v /opt/sfk/data_json:/import:ro -e QUEUE_CONNECTION=sync -e MAIL_MAILER=log \
    app php artisan legacy:import --path=/import
rm -rf /opt/sfk/data_json && aws s3 rm --recursive s3://<bucket>/import/
```

It prints each app with 作成 / 更新 / スキップ counts and is safe to run again. Open question before the real import:
the 製造済数量 rule (see the status report).

## 12. Backups and monitoring

- **Database**: RDS takes a daily backup and keeps 7 days with point-in-time restore. Before a risky change: *RDS →
  sfk-db → Actions → Take snapshot*. **Practise a restore once**: *Restore to point in time* → a new instance → check
  it → delete it.
- **Uploads**: S3 versioning keeps old and deleted files.
- **Server**: nothing on it needs a backup — `/opt/sfk/.env` and `app.env` are the only hand-made files; keep a copy
  of their values in the company password manager.
- *CloudWatch → Alarms*: EC2 `StatusCheckFailed_System` → action **Recover instance**; EC2 CPU > 80 % for 15 min;
  RDS `FreeStorageSpace` < 2 GB — all to your e-mail (an SNS topic).

## Everyday commands (Session Manager, `sudo -i`, `cd /opt/sfk`)

| Task | Command |
|---|---|
| Deploy | GitHub → Actions → Deploy to AWS → Run workflow |
| Roll back | `./deploy.sh <previous commit sha>` (the tags are commit shas; *ECR → sfk-platform* lists them) |
| What is running | `docker compose ps`, `grep APP_TAG .env` |
| Logs | `docker compose logs -f --tail 100 app` (also `caddy`, `redis`) |
| Artisan | `docker compose exec app php artisan <command>` |
| Restart | `docker compose restart app` |
| After editing `app.env` | `docker compose up -d --force-recreate app` |

A deploy restarts the app container: expect ~10–30 s of downtime, so deploy outside working hours.

## Where to go next

1. **CloudWatch Logs**: send the containers' logs to CloudWatch (`awslogs` logging driver) instead of the disk.
2. **ECS Fargate + Application Load Balancer + ACM**: no server to patch, zero-downtime deploys, certificates from
   AWS (the same image runs there; the ALB adds ~$20 a month).
3. **RDS Multi-AZ** once the system is the company's daily tool (doubles the database cost).
