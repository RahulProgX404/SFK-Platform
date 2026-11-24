(function(){"use strict";const g={INDEX:{LIST:"app.record.index.show",EDIT:"app.record.index.edit.show"},DETAIL:{SHOW:"app.record.detail.show"},CREATE:{SHOW:"app.record.create.show",CHANGE:"app.record.create.change",SUCCESS:"app.record.create.submit.success"},EDIT:{SHOW:"app.record.edit.show",CHANGE:"app.record.edit.change",SUCCESS:"app.record.edit.submit.success"}},C={DETAIL:{SHOW:"mobile.app.record.detail.show"},CREATE:{SHOW:"mobile.app.record.create.show",CHANGE:"mobile.app.record.create.change"},EDIT:{SHOW:"mobile.app.record.edit.show",CHANGE:"mobile.app.record.edit.change"}};function U(r){return Array.isArray(r)&&r.length>0&&typeof r[0]=="string"?r.map(e=>({name:e})):r}function v(r,e){return r.reduce((t,o)=>{const n=parseFloat(o[e]?.value||"0");return t+(isNaN(n)?0:n)},0)}function S(r){return(r<0?"-¥":"¥")+Math.abs(Math.round(r)).toLocaleString("ja-JP")}function G(r){return isFinite(r)?r.toFixed(1)+"%":"—"}function P(r){return r>0?"pos":r<0?"neg":""}const b={async callApi(r,e,t){try{return await kintone.api(kintone.api.url(r,!0),e,t)}catch(o){throw b.showErrorNotification(o),o}},async getAllRecords(r,e="",t=[]){let o=[],n=null;try{n=(await b.callApi("/k/v1/records/cursor","POST",{app:r,query:e,fields:t})).id;let i=!0;for(;i;){const l=await b.callApi("/k/v1/records/cursor","GET",{id:n});o=o.concat(l.records),i=l.next}return o}catch(s){throw b.showErrorNotification(s),s}finally{n&&await kintone.api(kintone.api.url("/k/v1/records/cursor",!0),"DELETE",{id:n}).catch(()=>{})}},showErrorNotification(r){console.error("KintoneHelper Error Trace:",r);let e="An unexpected error occurred within the system customization.";r&&typeof r.message=="string"&&(e=r.message);const t=kintone.app.getHeaderSpaceElement();if(t){const o=document.createElement("div");o.style.backgroundColor="#fb4c4c",o.style.color="#ffffff",o.style.padding="12px 24px",o.style.fontWeight="bold",o.style.borderRadius="4px",o.style.margin="10px 0",o.innerText=`⚠️ System Customization Alert: ${e}`,t.prepend(o)}else alert(`⚠️ System Error:

${e}`)}};class z{getCurrentAppId(){return kintone.app.getId()}async updateRecord(e,t,o){const n=await b.callApi("/k/v1/record.json","PUT",{app:e,id:t,record:o});return String(n.id)}async createRecord(e,t){const o=await b.callApi("/k/v1/record.json","POST",{app:e,record:t});return String(o.id)}async updateRecords(e,t){for(let o=0;o<t.length;o+=100){const n=t.slice(o,o+100);await b.callApi("/k/v1/records.json","PUT",{app:e,records:n})}}async getRecords(e,t,o){return(await b.callApi("/k/v1/records.json","GET",{app:e,query:t,fields:o})).records}async getAllIndexRecords(e,t,o){return b.getAllRecords(e,t,o||[])}async seedKintoneData({appId:e,data:t,fieldMapper:o={},existingRecords:n=[],skipIfNotEmpty:s=!0}){if(!Array.isArray(t))throw new Error("Data must be an array");if(s&&n.length>0){console.log("Skipping insert: records already exist");return}console.log("Upserting data...");const i=t.map(l=>{const m={};for(const y in l){const c=l[y],d=o[y]??y;m[d]={value:U(c)}}return m});await b.callApi("/k/v1/records","POST",{app:e,records:i}),alert("Upserting completed")}async deleteRecords(e){const t=kintone.app.getId();let o=e??[];if(o.length===0){const s=(kintone.app.getQuery()||"").replace(/\s+limit\s+\d+(\s+offset\s+\d+)?\s*$/i,"").trim(),i=[];let l=0;const m=500;for(;;){const c=(await kintone.api(kintone.api.url("/k/v1/records.json",!0),"GET",{app:t,query:`${s} limit ${m} offset ${l}`,fields:["$id"]})).records.map(d=>Number(d.$id.value));if(i.push(...c),c.length<m)break;l+=m}o=i}if(o.length===0){alert("削除対象レコードがありません。");return}if(!window.confirm(`${o.length}件のレコードを削除します。よろしいですか？`))throw new Error("ユーザーが削除をキャンセルしました。");for(let s=0;s<o.length;s+=100)await kintone.api(kintone.api.url("/k/v1/records.json",!0),"DELETE",{app:t,ids:o.slice(s,s+100)});alert(`${o.length}件削除しました。`),location.reload()}}function q(r=[],e){const t=[g.CREATE.SHOW,g.EDIT.SHOW,g.INDEX.EDIT,C.CREATE.SHOW,C.EDIT.SHOW,...r.map(o=>`${g.EDIT.CHANGE}.${o}`),...r.map(o=>`${g.CREATE.CHANGE}.${o}`),...r.map(o=>`${C.EDIT.CHANGE}.${o}`),...r.map(o=>`${C.CREATE.CHANGE}.${o}`)];kintone.events.on(t,o=>{const n=o.record;return r.forEach(s=>{n[s].disabled=!0}),o})}function j(r){const e={create:r,edit:r,detail:r,index:r};function t(n,s){const i=n.type.startsWith("mobile.");return s.forEach(l=>{try{i?kintone.mobile.app.record.setFieldShown(l,!1):kintone.app.record.setFieldShown(l,!1)}catch(m){console.warn(`[hide-fields] could not hide field "${l}":`,m)}}),n}function o(n,s){return s.length&&s.forEach(i=>{try{kintone.app.record.setFieldShown(i,!1,n.viewId)}catch(l){console.warn(`[hide-fields] could not hide index field "${i}":`,l)}}),n}kintone.events.on(["app.record.create.show","mobile.app.record.create.show"],n=>t(n,e.create)),kintone.events.on(["app.record.edit.show","mobile.app.record.edit.show"],n=>t(n,e.edit)),kintone.events.on(["app.record.detail.show","mobile.app.record.detail.show"],n=>t(n,e.detail)),kintone.events.on(["app.record.index.show","mobile.app.record.index.show"],n=>o(n,e.index))}const K={error:(o="エラーが発生しました")=>{kintone.showNotification("ERROR",o)},success:(o="処理が正常に完了しました")=>{kintone.showNotification("SUCCESS",o)},info:(o="お知らせがあります")=>{kintone.showNotification("INFO",o)}},E={orderEntryApp:{id:"$id",revision:"$revision",amPm:"AM_PM",fsc:"FSC",fscJudgement:"FSC該当判定",supplierFactory:"仕入先_製造場所_",bagSpec:"製袋予定規格",width:"左右",height:"天地",lid:"ふた",bottom:"底",layoutWidth:"展開寸法_左右_",layoutHeight:"展開寸法_天地_",envelopeType:"角・長・洋",otherOption:"その他の場合",sizeNo:"号",pastingMethod:"貼方",asPerArtwork:"原稿通り",dropdown1:"ドロップダウン_1",dieCut:"抜き",glueFlap:"口糊",sideGlue:"胴糊",diePlate:"抜型",windowType:"窓",windowWidth:"窓_左右_",windowHeight:"窓_天地_",fromLeft:"左から",fromBottom:"底から",caseNumber:"ケース番号",quantityPerUnit:"入り数",quantitySpecified:"入数指定",partitionCase:"仕切りケース",band:"帯",bandCount:"帯枚数",label:"ラベル",bagUnitPrice:"単価_製袋",inputMethod:"入力方法_1",manualAmount:"金額_手動__1",bagAmount:"金額_製袋",bagSubtotal:"金額_製袋_小計用",caseCost:"ケース代",shippingCost:"送料",pdfDraft:"PDF保存_原稿添付用_",pdfSalesCopy:"PDF保存_営業控_",pdfOrderSheet:"PDF保存_注文書_",totalPurchaseHidden:"total_purchase_hidden",status:"ステータス_0",designer:"デザイナー",designFee:"デザイン料",dataProvided:"データ支給",processingDetails:"トムソン・NO・シルク・箔",filmProvided:"フィルム支給",spare:"予備",supplierData:"仕入先_データ支給_",supplierFilm:"仕入先_フィルム支給_",supplierPlate:"仕入先_刷版_",purchaseCostTotal:"仕入原価計",purchaseTotal:"仕入合計",cuttingCost:"仕入断裁金額",plateCost:"仕入版代",spotColorCost:"仕入特色",storedPlateCost:"仕入置き版代",purchaseAmount:"仕入金額",clientAddress:"住所_得意先_",parentAddress:"住所_親会社_",createdAt:"作成日時",createdBy:"作成者",workSheetDraft:"作業明細表_原稿添付用_",workSheetSales:"作業明細表_営業控_",workSheetOrder:"作業明細表_注文書_",workerMemo:"作業者への伝言メモ",saveFlag:"保存区分",remarks:"備考",remarks0:"備考_0",plateRemarks:"備考_刷版_",category1:"分類1",category2:"分類2",profit:"利益",profitRate:"利益率",lastUsedDate:"前回使用日",previousOrderNo:"前回受注No",previousOrderQty:"前回受注数",previousOrderDate:"前回受注日",updateKey:"加算更新用キー",unit:"単位",plateUnitPrice:"単価_刷版_",printing:"印刷",printingLocation:"印刷場所",printingDate:"印刷日",clientId:"受注先の得意先ID",clientCode:"受注先の得意先コード",roundingType:"受注先の端数区分",recordId:"受注入力ID",orderDate:"受注日",oldSizeNo:"号_旧_",productName:"品名",salesHistory:"売上履歴",remainingSalesQty:"売上残数量",shippedQty:"売上済数量",saleAmount:"売上金額",completionDate:"完成日",salesAmountComplete:"売上金額済",actualCostTotal:"実原価合計",actualSalesAmount:"実売上金額",subtotal:"小計",processingClientId:"得意先ID_製袋・製本・他加工",clientName:"得意先_受注先_",assignee:"担当者",deliveryMethod:"持込便",deliveryLocation:"持込場所",deliveryDate:"持込日",quantity:"数量",versionType:"新旧版区分",time:"時間",updatedAt:"更新日時",updatedBy:"更新者",machine:"機械",machineCode:"機械コード",notes:"注意事項等",orderNumber:"注番",reportLinkKey:"注番_日報連携キー",plateType:"版区分",plateCount:"版数",currentSalesAmount:"現在売上済金額",orderNumberSalesAmount:"注番売上済金額",paperInfo:"用紙情報",supplierFactoryShort:"略名_仕入先_製造場所_",plateShortName:"略名_刷版",internalCost:"社内原価",deliveryNoteRecipient:"納品書送付先",dueDate:"納期",closingDate:"締日",estimatedCompletionTime:"製作完了見込み時間",manufacturingSales:"製造売上",manufacturingRemainingQty:"製造残数量",manufacturingCompletedQty:"製造済数量",sample:"見本",estimatedProfit:"見積利益",estimatedCostTotal:"見積原価合計",invoiceRecipient:"請求書送付先",clientZip:"郵便番号_得意先_",parentZip:"郵便番号_親会社_",plateAmount:"金額_刷版_",relatedRecordKey:"関連レコードキー"},dailyReportEntryApp:{id:"$id",worker:"作業者_0",overtimeRate:"残業単価",overtimeStartTime:"残業開始時間",overtimeEndTime:"残業終了時間",overtimeBreakTime:"残業休憩時間",workStartTime:"開始時間_0",workEndTime:"終了時間_0",workBreakTime:"休憩時間",workDate:"作業日",quantityUnitPrice:"枚数単価",moldChangeUnitPrice:"型替え単価",orderNo:"注番_0",internalCost:"社内原価"},salesMasterApp:{id:"$id",salesInputId:"売上入力ID",salesNo:"売上No",deliveryNumber:"納品書番号",invoiceNumber:"請求書番号",orderNo:"注番",customerId:"売上先の得意先ID",customerCode:"売上先の得意先コード",customerName:"売上先",closingDateDetermination:"締日判定",item:"品名",quantity:"売上数量",remainingQty:"売上残数量",deliveredQty:"現在売上済数量",unit:"単位",price:"売上単価",subtotal:"売上金額",taxRate:"税率",summary:"摘要",salesDate:"売上日",postalCode:"郵便番号_得意先_",postalCodeP:"郵便番号_P_",deliveryAddress:"納品先住所_得意先_",addressP:"住所_P_",staff:"担当者",noOfDelivery:"納品回数",noOfInvoice:"請求回数",actualDeliveryAddress:"住所_P_",accountsReceivableUpdateKeyByClosingDate:"締日売掛更新キー",salesAmount:"売上金額",salesAmountExcludingTax:"売上金額_税抜_",salesAmountIncludingTax:"売上金額_税込_",tax:"消費税",estimatedTotalCost:"見積原価合計",estimatedExpenses:"見積経費",salesAmount_apportioned:"売上金額_按分_",salesQty:"売上数量",orderedQty:"受注数量",orderNumber:"注番",invoiceCreation:"請求書作成",deliveryNumberCreation:"納品書作成"},purchaseEntry:{id:"$id",payableLinkageKey:"買掛連携キー",purchaseCategoryJudgement:"仕入カテゴリ判定",orderNo:"受注No",amount:"金額_0",cuttingAmount:"断裁金額",plateWoodMoldAmount:"版代",storedPlateAmount:"置き版代",spotColorOtherAmount:"特色",purchaseTotal:"仕入金額合計"}},x={shiraishiOrderEntryApp:86,shiraishiPurchaseEntry:87,shiraishiSalesMaster:88,shiraishiDailyReportEntry:686},a={ids:{orderEntryApp:x.shiraishiOrderEntryApp,salesMaster:x.shiraishiSalesMaster,purchaseEntry:x.shiraishiPurchaseEntry,dailyReportEntry:x.shiraishiDailyReportEntry},orderEntryApp:E.orderEntryApp,salesMasterApp:E.salesMasterApp,purchaseEntry:E.purchaseEntry,dailyReportEntryApp:E.dailyReportEntryApp,ALLOWED_USERS:new Set([])},O=[E.orderEntryApp.saleAmount,E.orderEntryApp.estimatedCostTotal,E.orderEntryApp.estimatedProfit,E.orderEntryApp.actualSalesAmount,E.orderEntryApp.internalCost,E.orderEntryApp.purchaseCostTotal,E.orderEntryApp.actualCostTotal],u="oeoo-summary-widget",Q="oeoo-summary-style",J="oeoo-summary-body",Y="oeoo-summary-toggle";class V{constructor(e){this.calculateProfitService=e}async calculateProfit(e){const t=a.ids.orderEntryApp,o=a.ids.salesMaster;try{const n=await this.calculateProfitService.getOrderEntryRecord(t,`$id = "${e}"`);if(!n||n.length===0){console.warn(`[CalculateProfit] No order entry record found for id="${e}". Skipping profit update.`);return}const s=n[0],i=s[a.orderEntryApp.orderNumber]?.value;if(!i){console.warn(`[CalculateProfit] Order record id="${e}" has no order number. Writing profit=0.`),await this.calculateProfitService.updateOrderEntryRecordProfitAmount(t,e,0);return}const l=await this.calculateProfitService.getSalesMasterRecord(o,`${a.salesMasterApp.orderNumber} = "${i}"`);if(!l||l.length===0){console.warn(`[CalculateProfit] No sales master record found for orderNumber="${i}". Writing profit=0.`),await this.calculateProfitService.updateOrderEntryRecordProfitAmount(t,e,0);return}const m=l[0],y=parseFloat(m[a.salesMasterApp.salesAmountIncludingTax]?.value||"0"),c=parseFloat(s[a.orderEntryApp.actualCostTotal]?.value||"0"),d=isNaN(y)?0:y,h=isNaN(c)?0:c,p=d-h;console.log(`[CalculateProfit] recordId=${e} | salesAmt=${d} | costTotal=${h} | profit=${p}`),await this.calculateProfitService.updateOrderEntryRecordSalesAmountComplete(t,e,d),await this.calculateProfitService.updateOrderEntryRecordProfitAmount(t,e,p)}catch(n){throw K.error("利益の計算に失敗しました。"),console.error(`[CalculateProfit] Failed to calculate profit for recordId="${e}":`,n),n}}}class X{constructor(e){this.kintoneRepo=e}async getSalesMasterRecord(e,t){return await this.kintoneRepo.getRecords(e,t)}async getOrderEntryRecord(e,t){return await this.kintoneRepo.getRecords(e,t)}async updateOrderEntryRecordProfitAmount(e,t,o){return await this.kintoneRepo.updateRecord(e,t,{[a.orderEntryApp.profit]:{value:o}})}async updateOrderEntryRecordSalesAmountComplete(e,t,o){return await this.kintoneRepo.updateRecord(e,t,{[a.orderEntryApp.salesAmountComplete]:{value:o}})}}const Z="sfk-toggle-fields-btn",$="sfk-toggle-fields-btn",D="sfk-toggle-fields-style",ee=`
.${$} {
  display: inline-flex;
  align-items: center;
  padding: 4px 2px;
  border: none;
  background: none;
  color: #1a73e8;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.${$}:hover { color: #0b57d0; text-decoration: underline; }
.${$}:focus-visible { outline: 2px solid #1a73e8; outline-offset: 2px; border-radius: 2px; }
`,te=[g.DETAIL.SHOW,g.CREATE.SHOW,g.EDIT.SHOW,C.DETAIL.SHOW,C.CREATE.SHOW,C.EDIT.SHOW];let H=0;function oe(r,e,t={}){const{initiallyOpen:o=!1,expandLabel:n="▼ 展開する",collapseLabel:s="▲ 折りたたむ"}=t;H+=1;const i=`${Z}-${H}`;kintone.events.on(te,l=>{const y=String(l.type).startsWith("mobile.")?kintone.mobile.app.record:kintone.app.record,c=k=>{e.forEach(A=>{try{y.setFieldShown(A,k)}catch(_){console.warn(`[toggleFields] could not toggle field "${A}":`,_)}})};let d=o;c(d);const h=y.getSpaceElement(r);if(!h)return console.warn(`[toggleFields] space field "${r}" not found on this form.`),l;if(h.querySelector(`#${i}`))return l;re();const p=document.createElement("button");p.type="button",p.id=i,p.className=$;const f=()=>{p.textContent=d?s:n,p.setAttribute("aria-expanded",String(d))};return p.addEventListener("click",()=>{d=!d,c(d),f()}),f(),h.appendChild(p),l})}function re(){if(document.getElementById(D))return;const r=document.createElement("style");r.id=D,r.textContent=ee,document.head.appendChild(r)}class ne{constructor(e){this.kintoneRepo=e}getAppId(){return a.ids.orderEntryApp}async getIndexRecords(e){const t=this.getAppId();return await this.kintoneRepo.getRecords(t,e,O)}async getAllIndexRecords(e){const t=this.getAppId();return await this.kintoneRepo.getAllIndexRecords(t,e,O)}}class ae{constructor(e){this.options=e;const{id:t,label:o="Custom Button",className:n}=e;this.button=document.createElement("button"),this.button.id=t,this.button.textContent=o,n&&(this.button.className=n)}render(e){e.appendChild(this.button)}onClick(e){this.button.addEventListener("click",e)}disable(){this.button.disabled=!0}enable(){this.button.disabled=!1}setLabel(e){this.button.textContent=e}remove(){this.button.remove()}getElement(){return this.button}}const ie=r=>!!document.getElementById(r);function N(r,e){if(document.getElementById(r))return;const t=document.createElement("style");t.id=t.textContent="",t.id=r,t.textContent=e,document.head.appendChild(t)}function se(r){return r?kintone.mobile.app.getHeaderSpaceElement():kintone.app.getHeaderSpaceElement()}function L(r){const{widgetId:e,isMobile:t=!1,css:o,styleId:n}=r;if(!e)return console.error("[SummaryDisplay] widgetId is required for widget mode"),null;N(n,o),document.getElementById(e)?.remove();const s=se(t);if(!s)return null;const i=document.createElement("div");return i.id=e,s.appendChild(i),i}function le(r,e){const{toggleId:t,bodyId:o,title:n}=e;r.innerHTML=`
    <div class="oeoo-bar" ${t?`id="${t}"`:""}>
      <span class="oeoo-bar-title">📊 ${n}</span>
      <span class="oeoo-bar-badge">読み込み中...</span>
    </div>
    <div class="oeoo-body" ${o?`id="${o}"`:""}>
      <div class="oeoo-state">
        <div class="oeoo-spinner"></div>
        集計中...
      </div>
    </div>
  `}function de(r,e,t,o,n){const{toggleId:s,bodyId:i,title:l}=n,m=t.length>0?t.map(f=>`${f.label}: ${f.value}`).join("　"):`${o.toLocaleString("ja-JP")} 件`,y=e.map(({label:f,value:k,colorClass:A=""})=>`
        <div class="oeoo-cell">
          <span class="oeoo-label">${f}</span>
          <span class="oeoo-value ${A}">${k}</span>
        </div>
      `).join("");r.innerHTML=`
    <div class="oeoo-bar" ${s?`id="${s}"`:""}>
      <span class="oeoo-bar-title">📊 ${l}</span>
      <span class="oeoo-bar-badge">${m}</span>
      <span class="oeoo-bar-chevron open" id="oeoo-chevron">▲</span>
    </div>
    <div class="oeoo-body" ${i?`id="${i}"`:""}>
      ${y}
    </div>
  `;const c=s?document.getElementById(s):r.querySelector(".oeoo-bar"),d=i?document.getElementById(i):r.querySelector(".oeoo-body"),h=r.querySelector("#oeoo-chevron");let p=!1;c&&d&&c.addEventListener("click",()=>{p=!p,d.classList.toggle("hidden",p),h?.classList.toggle("open",!p)})}function ce(r,e,t){const o=t?document.getElementById(t):r.querySelector(".oeoo-body");o&&(o.innerHTML=`
      <div class="oeoo-state" style="color:#c0392b;">
        ⚠️ ${e}
      </div>
    `)}function pe(r,e,t){const{overlayId:o,title:n}=t;if(!o){console.error("[SummaryDisplay] overlayId is required for modal mode");return}document.getElementById(o)?.remove();const s=()=>document.getElementById(o)?.remove(),i=document.createElement("tbody");r.forEach(({label:w,value:I,colorClass:T="",separatorAfter:be})=>{const W=i.insertRow(),Ae=W.insertCell();Ae.textContent=w;const F=W.insertCell();if(F.textContent=I,F.className=["r",T].filter(Boolean).join(" "),be){const B=i.insertRow();B.className="sep",B.insertCell().colSpan=2}});const l=document.createElement("table");l.className="ses-tbl";const y=l.createTHead().insertRow();[["項目",""],["合計","r"]].forEach(([w,I])=>{const T=document.createElement("th");T.textContent=w,I&&(T.className=I),y.appendChild(T)}),l.appendChild(i);const c=document.createElement("div");c.id=o,c.addEventListener("click",w=>{w.target===c&&s()});const d=document.createElement("div");d.className="ses-modal";const h=document.createElement("div");h.className="ses-mh";const p=document.createElement("span");p.className="ses-mh-title",p.textContent=`📊 ${n}`;const f=document.createElement("button");f.className="ses-mh-x",f.textContent="×",f.addEventListener("click",s),h.appendChild(p),h.appendChild(f);const k=document.createElement("div");k.className="ses-body",k.appendChild(l);const A=document.createElement("div");A.className="ses-foot";const _=document.createElement("div");_.className="ses-note",_.innerHTML=e.map(w=>`${w.label}: <strong>${w.value}</strong>`).join("<br>");const R=document.createElement("button");R.className="ses-ok",R.textContent="閉じる",R.addEventListener("click",s),A.appendChild(_),A.appendChild(R),d.appendChild(h),d.appendChild(k),d.appendChild(A),c.appendChild(d),document.body.appendChild(c)}class ue{constructor(){this.widget=null}showLoading(e){e.mode==="widget"&&(this.widget=L(e),this.widget&&le(this.widget,e))}show({metrics:e,footerNotes:t=[],recordCount:o,options:n}){const s=t.length>0?t:[{label:"対象件数",value:`${o.toLocaleString("ja-JP")} 件`}];if(n.mode==="widget"){const i=this.widget??L(n);this.widget=i,i&&de(i,e,s,o,n)}else N(n.styleId,n.css),pe(e,s,n)}showError(e,t){t.mode==="widget"&&this.widget?ce(this.widget,e,t.bodyId):alert(`集計に失敗しました:
${e}`),console.error("[SummaryDisplay]",e)}mountButton(e,t){if(e.mode!=="modal")return;const o=e.buttonId??"ks-summary-btn",n=e.buttonLabel??"📊 集計サマリー";if(ie(o))return;N(e.styleId,e.css);const s=e.isMobile??!1?kintone.mobile.app.getHeaderMenuSpaceElement():kintone.app.getHeaderMenuSpaceElement();if(!s)return;const i=new ae({id:o,label:n,className:"gaia-ui-actionmenu-save"});i.render(s),i.onClick(async()=>{i.disable(),i.setLabel("読み込み中...");try{await t()}catch(l){this.showError(l instanceof Error?l.message:String(l),e)}finally{i.enable(),i.setLabel(n)}})}}const me=`
/* ── Wrapper ── */
#${u} {
  width: 100%;
  background: #3498db;

  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Meiryo, sans-serif;
  font-size: 12px;
  box-sizing: border-box;
  border-bottom: 2px solid #0e4a8a;
}

/* ── Title bar ── */
#${u} .oeoo-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 14px;
  cursor: pointer;
  user-select: none;
}
#${u} .oeoo-bar-title {
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.04em;
}
#${u} .oeoo-bar-badge {
  background: rgba(255,255,255,0.20);
  color: #e8f0fa;
  font-size: 10px;
  font-weight: 600;
  padding: 1px 7px;
  border-radius: 10px;
  white-space: nowrap;
}
#${u} .oeoo-bar-chevron {
  margin-left: auto;
  color: rgba(255,255,255,0.7);
  font-size: 11px;
  transition: transform 0.2s;
}
#${u} .oeoo-bar-chevron.open {
  transform: rotate(180deg);
}

/* ── Metrics grid ── */
#${u} .oeoo-body {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  background: #ffffff;
  border-top: 1px solid #ccdcef;
}
#${u} .oeoo-body.hidden {
  display: none;
}
#${u} .oeoo-cell {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  padding: 8px 14px;
  border-right: 1px solid #e5edf5;
  gap: 2px;
}
#${u} .oeoo-cell:last-child {
  border-right: none;
}
#${u} .oeoo-label {
  font-size: 10px;
  font-weight: 600;
  color: #5a6a7e;
  white-space: nowrap;
  letter-spacing: 0.03em;
}
#${u} .oeoo-value {
  font-size: 13px;
  font-weight: 700;
  color: #1a2733;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
#${u} .oeoo-value.pos { color: #1a6e33; }
#${u} .oeoo-value.neg { color: #c0392b; }
#${u} .oeoo-value.large { font-size: 14px; }

/* ── Loading / error states ── */
#${u} .oeoo-state {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  font-size: 12px;
  color: #5a6a7e;
}
#${u} .oeoo-spinner {
  width: 14px; height: 14px;
  border: 2px solid #d0dde9;
  border-top-color: #2e8ded;
  border-radius: 50%;
  animation: oeoo-spin 0.7s linear infinite;
  flex-shrink: 0;
}
@keyframes oeoo-spin { to { transform: rotate(360deg); } }
`;class ye{constructor(e){this.oeooService=e,this.display=new ue}async aggregate(){const e=kintone.app.getQueryCondition()??"",t=await this.oeooService.getAllIndexRecords(e),o=a.orderEntryApp,n=v(t,o.saleAmount),s=v(t,o.estimatedCostTotal),i=v(t,o.estimatedProfit),l=v(t,o.actualSalesAmount),m=v(t,o.internalCost),y=v(t,o.purchaseCostTotal),c=v(t,o.actualCostTotal),d=l!==0?(l-c)/l*100:NaN;return{saleAmount:n,estimatedCostTotal:s,estimatedProfit:i,actualSalesAmount:l,internalCost:m,purchaseCostTotal:y,actualCostTotal:c,profitRate:d,recordCount:t.length}}buildMetrics(e){return[{label:"売上金額",value:S(e.saleAmount)},{label:"見積原価合計",value:S(e.estimatedCostTotal)},{label:"見積利益",value:S(e.estimatedProfit),colorClass:P(e.estimatedProfit)},{label:"実売上金額",value:S(e.actualSalesAmount)},{label:"社内原価",value:S(e.internalCost)},{label:"仕入原価計",value:S(e.purchaseCostTotal)},{label:"実原価合計",value:S(e.actualCostTotal)},{label:"利益率",value:G(e.profitRate),colorClass:`${P(e.profitRate)} large`}]}async initialize(){const e={mode:"widget",title:"集計サマリー",css:me,styleId:Q,widgetId:u,bodyId:J,toggleId:Y};this.display.showLoading(e);try{const t=await this.aggregate();this.display.show({metrics:this.buildMetrics(t),footerNotes:[{label:"対象件数",value:`${t.recordCount.toLocaleString("ja-JP")} 件`}],recordCount:t.recordCount,options:e})}catch(t){this.display.showError(t instanceof Error?t.message:String(t),e)}}}q([a.orderEntryApp.profit,a.orderEntryApp.salesAmountComplete]),j([a.orderEntryApp.totalPurchaseHidden]),oe("switch_display_bags",[a.orderEntryApp.supplierFactory,a.orderEntryApp.bagSpec,a.orderEntryApp.width,a.orderEntryApp.height,a.orderEntryApp.lid,a.orderEntryApp.bottom,a.orderEntryApp.layoutWidth,a.orderEntryApp.layoutHeight,a.orderEntryApp.envelopeType,a.orderEntryApp.otherOption,a.orderEntryApp.sizeNo,a.orderEntryApp.pastingMethod,a.orderEntryApp.asPerArtwork,a.orderEntryApp.dropdown1,a.orderEntryApp.dieCut,a.orderEntryApp.glueFlap,a.orderEntryApp.sideGlue,a.orderEntryApp.diePlate,a.orderEntryApp.windowType,a.orderEntryApp.windowWidth,a.orderEntryApp.windowHeight,a.orderEntryApp.fromLeft,a.orderEntryApp.fromBottom,a.orderEntryApp.caseNumber,a.orderEntryApp.quantityPerUnit,a.orderEntryApp.quantitySpecified,a.orderEntryApp.partitionCase,a.orderEntryApp.band,a.orderEntryApp.bandCount,a.orderEntryApp.label,a.orderEntryApp.bagUnitPrice,a.orderEntryApp.inputMethod,a.orderEntryApp.manualAmount,a.orderEntryApp.bagAmount,a.orderEntryApp.bagSubtotal,a.orderEntryApp.caseCost,a.orderEntryApp.shippingCost]);const M=new z,he=new X(M),ge=new V(he),fe=new ne(M),Ee=new ye(fe);kintone.events.on(g.INDEX.LIST,async function(r){return Ee.initialize(),r}),kintone.events.on(g.DETAIL.SHOW,async function(r){return r}),kintone.events.on([g.EDIT.SUCCESS,g.CREATE.SUCCESS],async function(r){const e=r.record.$id.value;return await ge.calculateProfit(e),r})})();
