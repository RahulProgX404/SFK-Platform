/*!
 * production-schedule.js — 製袋予定表 of 12. 製袋予定管理 (sfk-platform)
 * ---------------------------------------------------------------------------
 * The board of the app (ListBagSchedules, view 製袋予定表), taken over from the sfk-hub board on the page itself
 * (no masthead, backdrop or footer, user 2026-11-06): month bar (‹ month › and the 年/月 picker), 「1日」「7日」「30日」, the day table per line with inline add / edit and the
 * 2-step delete, the lines × days grids with add / edit popovers, drag and drop (mouse; long press on touch) with
 * 「コピーしますか、移動しますか？」, background highlights, the company calendar (祝日 / 会社休日 / 半日出勤, Sundays closed)
 * and the loading skeleton.
 *
 * Data, the 注番 lookup and the calendar come from the server: adapters.livewire($wire) calls the page's
 * BagScheduleBoard component (Modules\Production\Filament\Resources\BagSchedules\Board). A 注番 fills 担当者 / 品名 / 部数
 * from 06. 売上入力; the calendar is the 会社カレンダー of 共通管理.
 *
 *   ProductionSchedule.mount(element, {
 *     ...ProductionSchedule.adapters.livewire($wire),   // dataSource, orderLookup, calendar
 *     lines, permissions: { canCreate, canEdit, canDelete }, syncUrl: true, urlParams: { date: "date", view: "span" },
 *   });
 *
 * Item: { id, date: "YYYY-MM-DD", line: "<line key>", personInitial, orderNumber, productName, quantity,
 *         notes: string[], highlightColor: string | null }
 * ---------------------------------------------------------------------------
 */
(function (root, factory) {
    if (typeof module === "object" && module.exports)
        module.exports = factory();
    else if (typeof define === "function" && define.amd) define([], factory);
    else root.ProductionSchedule = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
    "use strict";

    const VERSION = "1.0.0";

    /* =========================================================================
     * 1. FIXED CONFIGURATION (lib/production-schedule/presentation.ts)
     * ========================================================================= */

    /**
     * Machine lines — one row/section each. `key` is what an item's `line`
     * stores; never rename a key once real data exists.
     * `requiresManagerCheck` shows a warning instead of the empty state (6号機).
     */
    const PRODUCTION_LINES = [
        { key: "1号機", label: "1号機", color: "#0b489d", soft: "#dceaf6" },
        { key: "2号機", label: "2号機", color: "#3b6ea5", soft: "#e5edf6" },
        {
            key: "5号機（175幅まで）",
            label: "5号機",
            subtitle: "175幅まで",
            color: "#666f6c",
            soft: "#eceae1",
        },
        {
            key: "6号機",
            label: "6号機",
            color: "#b8791f",
            soft: "#f7ecd3",
            requiresManagerCheck: true,
            managerCheckMessage: "予定を入れる際は必ず工場長に相談して下さい",
        },
        {
            key: "3号機（245幅まで）",
            label: "3号機",
            subtitle: "245幅まで",
            color: "#7c5cbf",
            soft: "#efe9f8",
        },
        {
            key: "3-2号機・4号機（310幅まで）",
            label: "3-2号機・4号機",
            subtitle: "310幅まで",
            color: "#b25a7a",
            soft: "#f7e9ee",
        },
        {
            key: "カマス3号機　ダイア／ミシン／内・外カマス",
            label: "カマス3号機",
            subtitle: "ダイア／ミシン／内・外カマス",
            color: "#2f8f5b",
            soft: "#e1f4e9",
        },
        {
            key: "カマス2号機　ダイア／内カマス／間欠　※外カマス不可",
            label: "カマス2号機",
            subtitle: "ダイア／内カマス／間欠　※外カマス不可",
            color: "#1f6f8c",
            soft: "#e1eff3",
        },
        {
            key: "カマス1号機　内・外カマス　※ダイア不可",
            label: "カマス1号機",
            subtitle: "内・外カマス　※ダイア不可",
            color: "#8c5a2f",
            soft: "#f3e9e1",
        },
        {
            key: "ロファ窓（FWC20）",
            label: "ロファ窓",
            subtitle: "FWC20",
            color: "#5a6b8c",
            soft: "#e7eaf1",
        },
        {
            key: "ロファ窓（S3）",
            label: "ロファ窓",
            subtitle: "S3",
            color: "#6b8c5a",
            soft: "#eaf1e7",
        },
        { key: "窓131", label: "窓131", color: "#8c2f6b", soft: "#f3e1ec" },
        {
            key: "アラビア",
            label: "アラビア",
            color: "#2f5a8c",
            soft: "#e1e9f3",
        },
        {
            key: "アドヘア",
            label: "アドヘア",
            color: "#6b2f8c",
            soft: "#ece1f3",
        },
        {
            key: "平台（440幅まで）",
            label: "平台",
            subtitle: "440幅まで",
            color: "#8c6b2f",
            soft: "#f3ece1",
        },
        {
            key: "テープ/HM　※外カマス不可",
            label: "テープ/HM",
            subtitle: "※外カマス不可",
            color: "#2f8c7d",
            soft: "#e1f3f0",
        },
        {
            key: "手差しTP/内職",
            label: "手差しTP/内職",
            color: "#8c2f2f",
            soft: "#f3e1e1",
        },
    ];

    /** Fixed highlight palette — `key` is what `highlightColor` stores. */
    const HIGHLIGHT_COLORS = [
        { key: "yellow", label: "黄", swatch: "#e8b923", soft: "#fdf1c7" },
        { key: "rose", label: "赤", swatch: "#c0392b", soft: "#fbe0dd" },
        { key: "orange", label: "橙", swatch: "#d97a25", soft: "#fbe3cc" },
        { key: "green", label: "緑", swatch: "#2f8f5b", soft: "#dcf1e4" },
        { key: "blue", label: "青", swatch: "#3b6ea5", soft: "#dde9f5" },
        { key: "purple", label: "紫", swatch: "#7c5cbf", soft: "#eae2f7" },
    ];

    /** Sunday-first, matching the day rail. */
    const DOW_JP = ["日", "月", "火", "水", "木", "金", "土"];
    const MONTHS_JP = [
        "1月",
        "2月",
        "3月",
        "4月",
        "5月",
        "6月",
        "7月",
        "8月",
        "9月",
        "10月",
        "11月",
        "12月",
    ];

    const CALENDAR_DAY_TYPES = [
        "NATIONAL_HOLIDAY",
        "COMPANY_HOLIDAY",
        "HALF_DAY",
    ];
    const CALENDAR_DAY_PRESENTATION = {
        NATIONAL_HOLIDAY: { shortLabel: "祝", label: "祝日", symbol: "○" },
        COMPANY_HOLIDAY: { shortLabel: "休", label: "会社休日", symbol: "○" },
        HALF_DAY: { shortLabel: "半", label: "半日出勤", symbol: "△" },
    };
    const CALENDAR_TYPE_ALIASES = {
        祝日: "NATIONAL_HOLIDAY",
        national: "NATIONAL_HOLIDAY",
        national_holiday: "NATIONAL_HOLIDAY",
        会社休日: "COMPANY_HOLIDAY",
        休日: "COMPANY_HOLIDAY",
        company: "COMPANY_HOLIDAY",
        company_holiday: "COMPANY_HOLIDAY",
        半日出勤: "HALF_DAY",
        半日: "HALF_DAY",
        half: "HALF_DAY",
        half_day: "HALF_DAY",
    };

    /** Every user-visible string that is likely to need changing per project. */
    const DEFAULT_TEXT = {
        title: "製袋予定表",
        holidayNote:
            "この日は休日として設定されているため、製袋予定は表示されません。会社カレンダー（共通管理 > 会社カレンダー）で変更できます。",
        managerCheckDefault: "予定を入れる際は必ず工場長に相談してください",
        lookupPending: "売上入力を検索中…",
        lookupHelp:
            "担当・品名・部数は06. 売上入力の一致するレコードから自動的に入力されます。",
        manualHelp:
            "注番が未入力のため、06. 売上入力へは問い合わせません。担当・品名・部数はここで入力した内容がそのまま保存されます。",
        lookupButtonTitle:
            "入力した注番で06. 売上入力を検索し、担当・品名・部数を自動入力して保存します",
        manualButtonTitle:
            "入力した内容をそのまま保存します（06. 売上入力へは問い合わせません）",
        lookupNotConfigured:
            "06. 売上入力との連携が設定されていません。管理者に確認してください。",
        lookupFailed: "06. 売上入力への問い合わせに失敗しました。",
        lookupNotFound:
            "注番「{orderNumber}」に一致するレコードが06. 売上入力に見つかりません。",
        manualOrderLabel: "手動入力",
        loadFailed: "予定を読み込めませんでした。",
    };

    /* =========================================================================
     * 2. DATE MATHS (lib/calendar/grid.ts) — everything in local time
     * ========================================================================= */

    /** Local midnight for `value` (YYYY-MM-DD); today when absent or junk. */
    function parseAnchor(value, now) {
        const base = now || new Date();
        const today = new Date(
            base.getFullYear(),
            base.getMonth(),
            base.getDate(),
        );
        const match =
            typeof value === "string"
                ? value.match(/^(\d{4})-(\d{2})-(\d{2})$/)
                : null;
        if (!match) return today;
        const year = Number(match[1]);
        const month = Number(match[2]) - 1;
        const day = Number(match[3]);
        const parsed = new Date(year, month, day);
        if (Number.isNaN(parsed.getTime()) || parsed.getMonth() !== month)
            return today;
        return parsed;
    }

    /** `YYYY-MM-DD` in local time — the inverse of `parseAnchor`. */
    function dateParam(date) {
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, "0"),
            String(date.getDate()).padStart(2, "0"),
        ].join("-");
    }

    /** Monday of the week containing `date`. */
    function startOfWeek(date) {
        const offset = (date.getDay() + 6) % 7;
        return new Date(
            date.getFullYear(),
            date.getMonth(),
            date.getDate() - offset,
        );
    }

    /** Days of the anchor's month, padded to whole Monday-start weeks. */
    function buildMonthGrid(anchor) {
        const year = anchor.getFullYear();
        const month = anchor.getMonth();
        const start = startOfWeek(new Date(year, month, 1));
        const days = [];
        for (let index = 0; index < 42; index += 1) {
            days.push(
                new Date(
                    start.getFullYear(),
                    start.getMonth(),
                    start.getDate() + index,
                ),
            );
        }
        return days[35].getMonth() === month ? days : days.slice(0, 35);
    }

    function buildWeekGrid(anchor) {
        const start = startOfWeek(anchor);
        return Array.from(
            { length: 7 },
            (_, index) =>
                new Date(
                    start.getFullYear(),
                    start.getMonth(),
                    start.getDate() + index,
                ),
        );
    }

    function daysInMonth(anchor) {
        return new Date(
            anchor.getFullYear(),
            anchor.getMonth() + 1,
            0,
        ).getDate();
    }

    /** Every actual day of the anchor's month, unpadded (30日 view columns). */
    function buildMonthDays(anchor) {
        const total = daysInMonth(anchor);
        return Array.from(
            { length: total },
            (_, index) =>
                new Date(anchor.getFullYear(), anchor.getMonth(), index + 1),
        );
    }

    /** The Monday-start weeks of the anchor's month (7日 view's week selector). */
    function buildMonthWeekRows(anchor) {
        const days = buildMonthGrid(anchor);
        const rows = [];
        for (let index = 0; index < days.length; index += 7)
            rows.push(days.slice(index, index + 7));
        return rows;
    }

    function parseScheduleView(value) {
        if (value === "day") return "day";
        if (value === "month") return "month";
        return "week";
    }

    function formatDateLabel(dateKey) {
        const date = parseAnchor(dateKey);
        return `${date.getMonth() + 1}月${date.getDate()}日`;
    }

    /* =========================================================================
     * 3. COMPANY CALENDAR (lib/company-calendar/presentation.ts)
     * ========================================================================= */

    function normalizeDayType(value) {
        if (value == null || value === "") return null;
        const raw = String(value).trim();
        if (CALENDAR_DAY_TYPES.includes(raw)) return raw;
        return (
            CALENDAR_TYPE_ALIASES[raw] ||
            CALENDAR_TYPE_ALIASES[raw.toLowerCase()] ||
            null
        );
    }

    function isFullyClosed(type) {
        return type === "NATIONAL_HOLIDAY" || type === "COMPANY_HOLIDAY";
    }

    function isHalfDay(type) {
        return type === "HALF_DAY";
    }

    /** Explicit marking wins; otherwise every Sunday is closed. */
    function effectiveCalendarDayType(date, explicit) {
        if (explicit) return explicit;
        return date.getDay() === 0 ? "NATIONAL_HOLIDAY" : null;
    }

    function buildEffectiveDayTypeMap(days, explicit) {
        const effective = new Map();
        for (const day of days) {
            const key = dateParam(day);
            const type = effectiveCalendarDayType(day, explicit.get(key));
            if (type) effective.set(key, type);
        }
        return effective;
    }

    /* =========================================================================
     * 4. ORDER NUMBER FORMAT (123 -> sfk-0000123)
     * ========================================================================= */

    function createOrderNumberFormatter(config) {
        const prefix = config && config.prefix != null ? config.prefix : "sfk-";
        const digits = config && config.digits ? config.digits : 7;
        const inputPattern = new RegExp(`^\\d{1,${digits}}$`);
        const storedPattern = new RegExp(
            `^${escapeRegExp(prefix)}(\\d{${digits}})$`,
        );
        return {
            prefix,
            digits,
            inputPattern,
            /** "123" -> "sfk-0000123"; null for anything that isn't 1-N digits. */
            format(input) {
                const trimmed = String(input || "").trim();
                if (!inputPattern.test(trimmed)) return null;
                return `${prefix}${trimmed.padStart(digits, "0")}`;
            },
            /** "sfk-0000123" -> "123" (falls back to the input unchanged). */
            strip(orderNumber) {
                const match = storedPattern.exec(orderNumber || "");
                if (!match) return orderNumber || "";
                return match[1].replace(/^0+(?=\d)/, "");
            },
        };
    }

    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    }

    /* =========================================================================
     * 5. VALIDATION (lib/production-schedule/validations.ts)
     * ========================================================================= */

    function validateItemInput(input, lines, fmt) {
        const fail = (error) => ({ ok: false, error });
        const str = (value) => (value == null ? "" : String(value).trim());
        const value = {
            id: input.id ? String(input.id) : undefined,
            date: String(input.date || ""),
            line: String(input.line || ""),
            orderNumberInput: str(input.orderNumberInput),
            personInitial: str(input.personInitial),
            productName: str(input.productName),
            quantity: str(input.quantity),
            notes: Array.isArray(input.notes)
                ? input.notes.map(str).filter((note) => note.length > 0)
                : [],
        };

        if (!/^\d{4}-\d{2}-\d{2}$/.test(value.date))
            return fail("日付の形式が正しくありません");
        if (!lines.some((line) => line.key === value.line))
            return fail("ラインを選択してください");
        if (
            value.orderNumberInput !== "" &&
            !fmt.inputPattern.test(value.orderNumberInput)
        ) {
            return fail(
                `注番は数字のみ、${fmt.digits}桁以内で入力してください`,
            );
        }
        if (value.personInitial.length > 40)
            return fail("担当者は40文字以内で入力してください");
        if (value.productName.length > 120)
            return fail("品名は120文字以内で入力してください");
        if (value.quantity.length > 40)
            return fail("部数は40文字以内で入力してください");
        if (value.notes.some((note) => note.length > 40))
            return fail("備考は1件40文字以内で入力してください");
        if (value.notes.length > 10) return fail("備考は10件までです");

        if (!value.orderNumberInput) {
            if (!value.productName)
                return fail("注番が未入力の場合は品名を入力してください");
            if (!value.quantity)
                return fail("注番が未入力の場合は部数を入力してください");
        }
        return { ok: true, value };
    }

    /** Free-text note → pill color (the reference dashboard's `noteTag()`). */
    function noteTone(text) {
        if (text === "ハート") return "rose";
        if (text === "口糊無") return "amber";
        if (/×/.test(text)) return "blue";
        return "slate";
    }

    /* =========================================================================
     * 6. ERRORS / ITEMS / IDS
     * ========================================================================= */

    /** Throw this from an adapter to show `message` to the user verbatim. */
    class UserFacingError extends Error {
        constructor(message) {
            super(message);
            this.name = "UserFacingError";
            this.userFacing = true;
        }
    }

    function errorMessage(cause, fallback) {
        if (
            cause &&
            (cause instanceof UserFacingError || cause.userFacing === true) &&
            cause.message
        ) {
            return cause.message;
        }
        if (typeof console !== "undefined")
            console.error("[ProductionSchedule]", cause);
        return fallback;
    }

    function toDateKey(value) {
        if (value instanceof Date) return dateParam(value);
        const text = String(value || "");
        return text.length >= 10 ? text.slice(0, 10) : text;
    }

    function normalizeItem(raw) {
        const notes = Array.isArray(raw.notes)
            ? raw.notes
            : typeof raw.notes === "string" && raw.notes
              ? raw.notes.split(/\r?\n|[、,]/)
              : [];
        return {
            id: String(raw.id),
            date: toDateKey(raw.date),
            line: String(raw.line || ""),
            personInitial:
                raw.personInitial == null ? "" : String(raw.personInitial),
            orderNumber: raw.orderNumber == null ? "" : String(raw.orderNumber),
            productName: raw.productName == null ? "" : String(raw.productName),
            quantity: raw.quantity == null ? "" : String(raw.quantity),
            notes: notes.map((note) => String(note).trim()).filter(Boolean),
            highlightColor: raw.highlightColor
                ? String(raw.highlightColor)
                : null,
        };
    }

    function compareItems(a, b) {
        if (a.date !== b.date) return a.date < b.date ? -1 : 1;
        if (a.orderNumber !== b.orderNumber)
            return a.orderNumber < b.orderNumber ? -1 : 1;
        return 0;
    }

    function generateId() {
        const random =
            globalThis.crypto && globalThis.crypto.randomUUID
                ? globalThis.crypto.randomUUID().replace(/-/g, "")
                : Math.random().toString(36).slice(2) +
                  Math.random().toString(36).slice(2);
        return `ps${Date.now().toString(36)}${random.slice(0, 12)}`;
    }

    /* =========================================================================
     * 7. ADAPTERS
     * ========================================================================= */

    /**
     * The board's data on the server: the page's BagScheduleBoard component ($wire). Every call answers
     * { ok: true, ... } or { ok: false, error } — the error is shown as it is.
     */
    function livewireAdapter(wire) {
        const call = async (method, ...args) => {
            const result = await wire[method](...args);
            if (!result || result.ok !== true) {
                // no message: the board shows its own for the action (「この予定を保存できませんでした。」 …)
                if (result && typeof result.error === "string" && result.error)
                    throw new UserFacingError(result.error);
                throw new Error(`[ProductionSchedule] ${method} failed`);
            }
            return result;
        };
        return {
            dataSource: {
                list: ({ from, to }) =>
                    call("scheduleItems", from, to).then(
                        (result) => result.items,
                    ),
                create: (item) =>
                    call("scheduleCreate", item).then((result) => result.item),
                update: (id, patch) =>
                    call("scheduleUpdate", id, patch).then(
                        (result) => result.item,
                    ),
                remove: (id) =>
                    call("scheduleRemove", id).then(() => undefined),
                copy: (id, date, line) =>
                    call("scheduleCopy", id, date, line).then(
                        (result) => result.item,
                    ),
            },
            orderLookup: (orderNumber) =>
                call("scheduleLookup", orderNumber).then(
                    (result) => result.match,
                ),
            calendar: ({ from, to }) =>
                call("scheduleCalendar", from, to).then(
                    (result) => result.days,
                ),
        };
    }

    /** A fixed calendar object, e.g. { "2026-01-01": "NATIONAL_HOLIDAY" }. */
    function staticCalendar(map) {
        return async () => map;
    }

    /* =========================================================================
     * 8. DOM HELPERS / ICONS
     * ========================================================================= */

    function cx() {
        return Array.prototype.filter.call(arguments, Boolean).join(" ");
    }

    function appendChildren(el, children) {
        for (const child of children) {
            if (child == null || child === false || child === true) continue;
            if (Array.isArray(child)) appendChildren(el, child);
            else el.append(child instanceof Node ? child : String(child));
        }
    }

    function h(tag, props) {
        const el = document.createElement(tag);
        if (props) {
            for (const key in props) {
                const value = props[key];
                if (value == null || value === false) continue;
                if (key === "class") el.className = value;
                else if (key === "style") {
                    if (typeof value === "string") el.style.cssText = value;
                    else
                        for (const prop in value)
                            if (value[prop] != null)
                                el.style[prop] = value[prop];
                } else if (key === "dataset") Object.assign(el.dataset, value);
                else if (
                    key.slice(0, 2) === "on" &&
                    typeof value === "function"
                ) {
                    el.addEventListener(key.slice(2).toLowerCase(), value);
                } else if (key === "html") el.innerHTML = value;
                else if (key === "value") el.value = value;
                else el.setAttribute(key, value === true ? "" : String(value));
            }
        }
        appendChildren(el, Array.prototype.slice.call(arguments, 2));
        return el;
    }

    const PRODUCT_ICON =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h16l-1.5 12h-13z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/></svg>';
    const QTY_ICON =
        '<svg viewBox="0 0 60 22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1,15 7,12 13,14 19,7 25,10 31,5 37,9 43,4 49,8 55,3 59,6"/></svg>';
    const X_ICON =
        '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

    /* =========================================================================
     * 9. STYLES (components/production-schedule/schedule-theme.module.css)
     * ========================================================================= */

    const FONT_HREF =
        "https://fonts.googleapis.com/css2?family=Shippori+Mincho:wght@500;600;800&family=Zen+Kaku+Gothic+New:wght@400;500;700;900&family=JetBrains+Mono:wght@400;500;700&display=swap";

    const CSS = `
.ps-theme {
  --ink: #141a21; --paper: #ffffff; --paper-dim: #f2f6f9; --line: #dbe2ea;
  --brand: #0b489d; --brand-deep: #0a3572; --brand-soft: #dceaf6;
  --sky: #bbe2e8; --sky-deep: #1f8fa3;
  --amber: #b8791f; --amber-soft: #f7ecd3; --stamp-red: #a4342a;
  --muted: #5c6672; --bg-dark: #0a1524;
  /* on the page itself (no dark backdrop): the platform's colours, so light and dark themes both read */
  --page-ink: var(--sfk-ink, #141a21); --page-muted: var(--sfk-muted, #5c6672); --page-line: var(--sfk-line, #dbe2ea);
  --page-surface: var(--sfk-surface, #ffffff); --page-hover: var(--sfk-hover, #f2f6f9);
  --c-blue: #3b6ea5; --c-blue-soft: #e5edf6; --c-purple: #7c5cbf; --c-purple-soft: #efe9f8;
  --c-rose: #b25a7a; --c-rose-soft: #f7e9ee; --c-slate: #666f6c; --c-slate-soft: #eceae1;
  --font-ps-serif: "Shippori Mincho", "Hiragino Mincho ProN", "Yu Mincho", serif;
  --font-ps-body: "Zen Kaku Gothic New", "Hiragino Sans", "Yu Gothic", "Meiryo", sans-serif;
  --font-ps-mono: "JetBrains Mono", ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
  font-family: var(--font-ps-body);
  line-height: 1.5;
  -webkit-text-size-adjust: 100%;
}
.ps-theme, .ps-theme *, .ps-theme *::before, .ps-theme *::after { box-sizing: border-box; }
:where(.ps-theme) button, :where(.ps-theme) input {
  font-family: inherit; font-size: 100%; line-height: inherit; color: inherit;
  margin: 0; padding: 0; background: transparent; border: 0;
}
:where(.ps-theme) button { cursor: pointer; text-align: inherit; }
:where(.ps-theme) button:disabled { cursor: default; }
:where(.ps-theme) h1, :where(.ps-theme) p { margin: 0; }
:where(.ps-theme) table { border-collapse: collapse; }
:where(.ps-theme) svg { display: block; }

.ps-wrap {
  background: transparent;
  color: var(--page-ink);
  border: 0;
  border-radius: 0;
  padding: 0;
}

/* ---------- Month bar ---------- */
.ps-monthBar { display: flex; align-items: center; justify-content: space-between; padding: 0 4px; margin-bottom: 10px; }
.ps-monthRight { display: flex; align-items: center; gap: 12px; }
.ps-monthTitle { font-family: var(--font-ps-serif), serif; color: var(--page-ink); font-size: 15px;
  font-weight: 600; letter-spacing: 0.05em; }
.ps-monthNavGroup { display: flex; align-items: center; gap: 6px; }
.ps-monthNavArrow { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px;
  border-radius: 7px; border: 1px solid var(--page-line); background: var(--page-surface);
  color: var(--brand); text-decoration: none; font-family: var(--font-ps-mono), monospace; font-size: 13px; line-height: 1; }
:where(.ps-theme) .ps-monthNavArrow { border: 1px solid var(--page-line); background: var(--page-surface); text-align: center; }
.ps-monthNavArrow:hover { background: var(--page-hover); color: var(--brand-deep); }
.ps-monthTitleBtn { background: none; border: none; cursor: pointer; display: inline-flex; align-items: center;
  gap: 5px; padding: 2px 4px; border-radius: 6px; }
.ps-monthTitleBtn:hover { background: var(--page-hover); }
.ps-monthTitleCaret { font-size: 9px; color: var(--page-muted); }

/* ---------- Day rail ---------- */
.ps-dayRail { display: flex; gap: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 4px 4px 18px; }
.ps-dayTab { position: relative; flex: 0 0 auto; width: 54px; display: flex; flex-direction: column; align-items: center;
  gap: 4px; background:var(--sfk-primary-soft); border: 1px solid var(--page-line); border-radius: 12px;
  padding: 8px 4px 9px; transition: background 0.15s ease, border-color 0.15s ease, transform 0.12s ease;
  text-decoration: none; cursor: pointer; line-height: 1.3; }
:where(.ps-theme) .ps-dayTab, :where(.ps-theme) .ps-weekTab { background: var(--sfk-primary-soft); border: 1px solid var(--page-line); }
.ps-dayTab .ps-dow { font-family: var(--font-ps-mono), monospace; font-size: 10px; color: var(--page-muted); letter-spacing: 0.05em; }
.ps-dayTab .ps-num { font-family: var(--font-ps-serif), serif; font-size: 16px; font-weight: 700; color: var(--page-ink); }
.ps-pip { width: 5px; height: 5px; border-radius: 50%; background: transparent; margin-top: 1px; }
.ps-dayTab.ps-hasData .ps-pip, .ps-weekTab.ps-hasData .ps-pip { background: #2fb8d4; box-shadow: 0 0 5px #2fb8d4; }
.ps-dayTab.ps-isWeekend .ps-dow { color: var(--stamp-red); opacity: 0.75; }
.ps-dayTab.ps-isHoliday { background: rgba(164, 52, 42, 0.16); border-color: rgba(164, 52, 42, 0.4); }
.ps-dayTab.ps-isHoliday .ps-dow, .ps-dayTab.ps-isHoliday .ps-num { color: var(--stamp-red); }
.ps-dayTab:hover { background: var(--page-hover); transform: translateY(-1px); }
.ps-dayTab.ps-isHoliday:hover { background: rgba(164, 52, 42, 0.24); }
.ps-dayTab.ps-isHalfDay { background: rgba(184, 121, 31, 0.14); border-color: rgba(184, 121, 31, 0.4); }
.ps-dayTab.ps-isHalfDay .ps-dow, .ps-dayTab.ps-isHalfDay .ps-num { color: var(--amber); }
.ps-dayTab.ps-isHalfDay:hover { background: rgba(184, 121, 31, 0.22); }
.ps-dayTab.ps-active { background: linear-gradient(160deg, var(--brand), var(--brand-deep)); border-color: transparent;
  box-shadow: 0 8px 18px -8px rgba(11, 72, 157, 0.7); }
.ps-dayTab.ps-active .ps-dow, .ps-dayTab.ps-active .ps-num { color: var(--paper); }
.ps-dayTab.ps-active.ps-isHoliday { box-shadow: 0 0 0 1.5px var(--stamp-red), 0 8px 18px -8px rgba(11, 72, 157, 0.7); }
.ps-dayTab.ps-isToday { outline: 2px solid var(--sky-deep); outline-offset: 1px; }

/* ---------- Week selector (7日 view) ---------- */
.ps-weekTab { position: relative; flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; gap: 3px;
  min-width: 108px; background: var(--sfk-primary-soft); border: 1px solid var(--page-line);
  border-radius: 12px; padding: 8px 12px 9px; transition: background 0.15s ease, border-color 0.15s ease, transform 0.12s ease;
  text-decoration: none; cursor: pointer; line-height: 1.3; }
.ps-weekTab .ps-weekLabel { font-family: var(--font-ps-mono), monospace; font-size: 10px; color: var(--page-muted); letter-spacing: 0.05em; }
.ps-weekTab .ps-weekRange { font-family: var(--font-ps-serif), serif; font-size: 14px; font-weight: 700; color: var(--page-ink); white-space: nowrap; }
.ps-weekTab:hover { background: var(--page-hover); transform: translateY(-1px); }
.ps-weekTab.ps-active { background: linear-gradient(160deg, var(--brand), var(--brand-deep)); border-color: transparent;
  box-shadow: 0 8px 18px -8px rgba(11, 72, 157, 0.7); }
.ps-weekTab.ps-active .ps-weekLabel, .ps-weekTab.ps-active .ps-weekRange { color: var(--paper); }
.ps-weekTab.ps-isHoliday { background: rgba(164, 52, 42, 0.16); border-color: rgba(164, 52, 42, 0.4); }
.ps-weekTab.ps-isHoliday .ps-weekLabel, .ps-weekTab.ps-isHoliday .ps-weekRange { color: var(--stamp-red); }
.ps-weekTab.ps-active.ps-isHoliday { box-shadow: 0 0 0 1.5px var(--stamp-red), 0 8px 18px -8px rgba(11, 72, 157, 0.7); }
.ps-weekTab.ps-isToday { outline: 2px solid var(--sky-deep); outline-offset: 1px; }

.ps-todayBadge, .ps-halfDayBadge { font-family: var(--font-ps-mono), monospace; font-size: 10px; font-weight: 700;
  color: var(--paper); background: var(--sky-deep); padding: 2px 8px; border-radius: 999px; letter-spacing: 0.05em; white-space: nowrap; }
.ps-halfDayBadge { background: var(--amber); }

/* ---------- Day panel ---------- */
.ps-weekBlock { margin-bottom: 30px; }
.ps-weekBlock:last-child { margin-bottom: 0; }
.ps-panelHead { display: flex; align-items: baseline; gap: 14px; margin: 4px 6px 14px; color: var(--page-ink); }
.ps-bigDate { font-family: var(--font-ps-serif), serif; font-size: 24px; font-weight: 800; }
.ps-dowTag { font-family: var(--font-ps-mono), monospace; font-size: 12px; background: var(--amber-soft); color: #7a5216;
  padding: 3px 10px; border-radius: 999px; }
.ps-divider { flex: 1; height: 1px; background: var(--page-line); }
.ps-total { font-family: var(--font-ps-mono), monospace; color: var(--page-muted); font-size: 12px; }

.ps-holidayShell { background: var(--paper); border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(16, 24, 40, 0.08);
  border: 1px solid var(--page-line); padding: 48px 24px; text-align: center; }
.ps-holidayStamp { display: inline-flex; align-items: center; gap: 8px; font-family: var(--font-ps-serif), serif; font-size: 20px;
  font-weight: 800; color: var(--stamp-red); padding: 6px 18px; border: 2px solid var(--stamp-red); border-radius: 8px; transform: rotate(-2deg); }
.ps-holidayNote { margin-top: 16px; font-family: var(--font-ps-mono), monospace; font-size: 12.5px; color: var(--muted); }
:where(.ps-theme) .ps-holidayNote { margin-top: 16px; }

/* ---------- Day table ---------- */
.ps-tableShell { background: var(--paper); border-radius: 10px; overflow-x: auto; overflow-y: hidden;
  -webkit-overflow-scrolling: touch; box-shadow: 0 1px 3px rgba(16, 24, 40, 0.08); border: 1px solid var(--page-line); }
.ps-table { width: 100%; border-collapse: collapse; font-family: var(--font-ps-body), sans-serif; color: var(--ink); }
.ps-table thead th { text-align: left; font-size: 12px; color: #e2ecf8; font-weight: 700; padding: 13px 16px; background: #0f2e52;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08); letter-spacing: 0.03em; white-space: nowrap; }
.ps-table thead th:not(:last-child) { border-right: 1px solid rgba(255, 255, 255, 0.1); }
.ps-table .ps-colQty { text-align: right; }
.ps-colOp { width: 96px; text-align: right; }
.ps-colNote { width: 190px; }
.ps-sectionRow td { padding: 10px 16px; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
.ps-sectionRowInner { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; }
.ps-sectionLeft { display: flex; align-items: center; }
.ps-sectionPill { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 700;
  padding: 5px 12px 5px 8px; border-radius: 999px; letter-spacing: 0.02em; white-space: nowrap; }
.ps-sectionIc { width: 16px; height: 16px; border-radius: 5px; display: inline-flex; align-items: center; justify-content: center;
  font-size: 9px; color: #fff; font-family: var(--font-ps-mono), monospace; font-weight: 700; }
.ps-sectionSubtitle { font-size: 11px; color: var(--muted); margin-left: 10px; white-space: nowrap; overflow: hidden;
  text-overflow: ellipsis; max-width: 320px; }
.ps-sectionCount { font-family: var(--font-ps-mono), monospace; font-size: 11px; color: var(--muted); margin-left: 10px; white-space: nowrap; }
.ps-sectionAdd, .ps-gridAddBtn { flex: 0 0 auto; font-family: var(--font-ps-mono), monospace; font-size: 11px; font-weight: 600;
  color: var(--muted); background: transparent; border: 1px dashed var(--line); border-radius: 999px; padding: 3px 10px; cursor: pointer; white-space: nowrap; }
:where(.ps-theme) .ps-sectionAdd, :where(.ps-theme) .ps-gridAddBtn { border: 1px dashed var(--line); padding: 3px 10px; }
.ps-sectionAdd:hover, .ps-gridAddBtn:hover { color: var(--ink); border-color: var(--muted); }
.ps-itemRow td { padding: 11px 16px; border-bottom: 1px solid rgba(0, 0, 0, 0.05); font-size: 13.5px; vertical-align: middle; }
.ps-itemRow td:not(:last-child) { border-right: 1px solid rgba(0, 0, 0, 0.07); }
.ps-itemRow:hover td { filter: brightness(0.97); }
.ps-orderText { display: flex; flex-direction: column; gap: 2px; line-height: 1.3; }
.ps-orderChip { font-family: var(--font-ps-mono), monospace; font-weight: 700; font-size: 12px; letter-spacing: 0.02em; }
.ps-handle { font-family: var(--font-ps-mono), monospace; font-size: 11px; font-weight: 600; opacity: 0.75; }
.ps-productCell { display: flex; align-items: center; gap: 10px; color: var(--ink); font-weight: 500; }
.ps-picon { width: 22px; height: 22px; border-radius: 7px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.ps-picon svg { width: 12px; height: 12px; }
.ps-productNameTxt { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 360px; }
.ps-qtyCell { display: inline-flex; align-items: center; gap: 7px; justify-content: flex-end; width: 100%; }
.ps-qtyCell svg { width: 30px; height: 13px; flex: 0 0 auto; }
.ps-qtyVal { font-family: var(--font-ps-mono), monospace; font-weight: 700; font-size: 13.5px; color: var(--ink); }
.ps-notePills { display: flex; flex-wrap: wrap; gap: 6px; }
.ps-pill { font-size: 11px; padding: 3px 9px; border-radius: 999px; font-weight: 600; white-space: nowrap; }
.ps-pillRose { background: var(--c-rose-soft); color: var(--c-rose); }
.ps-pillBlue { background: var(--c-blue-soft); color: var(--c-blue); }
.ps-pillSlate { background: var(--c-slate-soft); color: var(--c-slate); }
.ps-pillAmber { background: var(--amber-soft); color: var(--amber); }
.ps-pillNone { color: #c3ccd6; font-size: 12px; }
.ps-opCell { text-align: right; white-space: nowrap; }
.ps-opBtn { font-family: var(--font-ps-mono), monospace; font-size: 11px; font-weight: 600; color: var(--muted);
  background: transparent; border: none; cursor: pointer; padding: 2px 5px; }
:where(.ps-theme) .ps-opBtn { padding: 2px 5px; }
.ps-opBtn:hover { color: var(--ink); }
.ps-opBtnDanger:hover { color: var(--stamp-red); }
.ps-opError { margin-top: 4px; font-size: 11px; font-weight: 600; color: #a4342a; white-space: normal; }
.ps-emptyRow td { padding: 13px 16px; color: var(--muted); font-family: var(--font-ps-mono), monospace; font-size: 12px; }
.ps-stub { width: 14px; height: 2px; background: var(--line); display: inline-block; margin-right: 8px; vertical-align: middle; }
.ps-alertRow td { padding: 11px 16px; }
.ps-alertNote { padding: 10px 14px; border-radius: 10px; background: linear-gradient(135deg, #fff6e0, #fbe9c2);
  border: 1px solid #eccf8d; color: #7a5216; font-size: 12px; font-weight: 700; line-height: 1.6; display: flex; gap: 10px; align-items: flex-start; }
.ps-alertNote::before { content: "注意"; flex: 0 0 auto; background: var(--stamp-red); color: #fff; font-family: var(--font-ps-serif), serif;
  font-size: 11px; padding: 2px 6px; border-radius: 4px; }
.ps-formRow td { padding: 14px 16px; background: var(--paper-dim); }

/* ---------- View toggle ---------- */
.ps-viewToggle { flex: 0 0 auto; display: inline-flex; gap: 2px; background: var(--page-surface);
  border: 1px solid var(--page-line); border-radius: 999px; padding: 2px; }
.ps-viewToggleBtn { font-family: var(--font-ps-mono), monospace; font-size: 11px; font-weight: 700; color: var(--page-muted);
  padding: 4px 12px; border-radius: 999px; text-decoration: none; cursor: pointer; }
:where(.ps-theme) .ps-viewToggleBtn { padding: 4px 12px; }
.ps-viewToggleBtn:hover { color: var(--page-ink); }
.ps-viewToggleBtn.ps-active { background: linear-gradient(160deg, var(--brand), var(--brand-deep)); color: var(--paper); }

/* ---------- Grid (lines × days) ---------- */
.ps-gridScroll { background: var(--paper); border-radius: 10px; overflow: auto; max-height: 90vh; -webkit-overflow-scrolling: touch;
  box-shadow: 0 1px 3px rgba(16, 24, 40, 0.08); border: 1px solid var(--page-line); }
.ps-gridTable { border-collapse: separate; border-spacing: 0; font-family: var(--font-ps-body), sans-serif; color: var(--ink); width: 100%; }
:where(.ps-theme) .ps-gridTable { border-collapse: separate; }
.ps-gridCorner, .ps-gridDayHeader { position: sticky; top: 0; z-index: 2; background: #0f2e52; padding: 10px 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.1); white-space: nowrap; }
.ps-gridCorner { left: 0; z-index: 3; width: 168px; min-width: 168px; text-align: left; font-size: 12px; color: #e2ecf8; font-weight: 700; }
.ps-gridDayHeader { min-width: 190px; text-align: left; font-weight: normal; }
.ps-gridDayHeaderInner { display: flex; flex-direction: column; gap: 4px; }
.ps-gridDayHeaderTop { display: flex; align-items: baseline; gap: 8px; }
.ps-gridDayDate { font-family: var(--font-ps-serif), serif; font-size: 14px; font-weight: 800; color: var(--paper); }
.ps-gridDayDow { font-family: var(--font-ps-mono), monospace; font-size: 10px; color: #8ba3c4; }
.ps-gridDayHeader.ps-gridDayHeaderHoliday .ps-gridDayDate, .ps-gridDayHeader.ps-gridDayHeaderHoliday .ps-gridDayDow { color: #f3c9c4; }
.ps-gridDayHeader.ps-gridDayHeaderToday { background: #123c47; }
.ps-gridDayCount { font-family: var(--font-ps-mono), monospace; font-size: 10.5px; color: #8ba3c4; }
.ps-gridDayTypeBadge { display: inline-flex; align-items: center; gap: 3px; font-family: var(--font-ps-mono), monospace; font-size: 10px;
  font-weight: 700; color: var(--paper); background: var(--stamp-red); padding: 1px 7px; border-radius: 999px; letter-spacing: 0.03em; align-self: flex-start; }
.ps-gridDayTypeBadge.ps-gridDayTypeBadgeHalf { background: var(--amber); }
.ps-gridLineCell { position: sticky; left: 0; z-index: 1; width: 168px; min-width: 168px; padding: 10px 12px;
  border-bottom: 1px solid var(--line); border-right: 1px solid var(--line); vertical-align: top; }
.ps-gridLinePill { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; padding: 4px 10px 4px 6px;
  border-radius: 999px; letter-spacing: 0.02em; white-space: nowrap; }
.ps-gridLineIc { width: 14px; height: 14px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center;
  font-size: 8px; color: #fff; font-family: var(--font-ps-mono), monospace; font-weight: 700; flex: 0 0 auto; }
.ps-gridLineSubtitle { display: block; margin-top: 4px; font-size: 10.5px; color: var(--muted); line-height: 1.4; }
.ps-gridCell { min-width: 190px; padding: 8px 10px; border-bottom: 1px solid var(--line); border-right: 1px solid var(--line);
  vertical-align: top; transition: background-color 0.1s ease; }
.ps-gridCell.ps-gridCellDragOver { background: var(--brand-soft); box-shadow: inset 0 0 0 2px var(--brand); }
.ps-gridCellInner { display: flex; flex-direction: column; gap: 6px; }
.ps-gridHoliday { font-family: var(--font-ps-mono), monospace; font-size: 11px; color: var(--stamp-red); opacity: 0.7; }
.ps-gridAlert { font-family: var(--font-ps-mono), monospace; font-size: 10.5px; font-weight: 700; color: #7a5216;
  background: linear-gradient(135deg, #fff6e0, #fbe9c2); border: 1px solid #eccf8d; border-radius: 6px; padding: 3px 7px; line-height: 1.5; }
.ps-gridEmpty { font-family: var(--font-ps-mono), monospace; font-size: 11px; color: #c3ccd6; }
.ps-gridChip { display: flex; align-items: flex-start; gap: 6px; border-radius: 8px; border: 1px solid rgba(0, 0, 0, 0.06);
  background: var(--paper-dim); padding: 5px 6px; cursor: grab; touch-action: none; -webkit-touch-callout: none;
  -webkit-user-select: none; user-select: none; }
.ps-gridChip.ps-gridChipDragging { opacity: 0.4; }
.ps-gridChip.ps-gridChipPending { cursor: default; border-style: dashed; animation: psGridChipPendingPulse 1.1s ease-in-out infinite; }
@keyframes psGridChipPendingPulse { 0%, 100% { opacity: 0.65; } 50% { opacity: 0.9; } }
.ps-gridChipPendingLabel { font-family: var(--font-ps-mono), monospace; font-size: 9.5px; font-weight: 600; color: var(--muted); }
.ps-gridChipMain { flex: 1; display: flex; flex-direction: column; gap: 2px; text-align: left; background: none; border: none;
  padding: 0; cursor: pointer; min-width: 0; line-height: 1.35; }
:where(.ps-theme) .ps-gridChipMain { text-align: left; }
.ps-gridChipMain:disabled { cursor: default; }
.ps-gridChipOrderRow { display: flex; align-items: baseline; gap: 5px; }
.ps-gridChipOrder { font-family: var(--font-ps-mono), monospace; font-weight: 700; font-size: 10.5px; }
.ps-gridChipHandle { font-family: var(--font-ps-mono), monospace; font-size: 9.5px; font-weight: 600; opacity: 0.75; }
.ps-gridChipProduct { font-size: 12px; font-weight: 500; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ps-gridChipQty { font-family: var(--font-ps-mono), monospace; font-size: 11px; color: var(--muted); }
.ps-gridChipDelete { flex: 0 0 auto; background: none; border: none; color: var(--muted); cursor: pointer; font-size: 13px; line-height: 1; padding: 2px; }
:where(.ps-theme) .ps-gridChipDelete { padding: 2px; line-height: 1; }
.ps-gridChipDelete:hover { color: var(--stamp-red); }
.ps-gridChipConfirm { display: flex; flex-direction: column; gap: 3px; flex: 0 0 auto; }
.ps-gridChipConfirm button { font-family: var(--font-ps-mono), monospace; font-size: 9.5px; font-weight: 700; background: none; border: none;
  cursor: pointer; padding: 1px 2px; text-align: right; color: var(--muted); }
.ps-gridChipConfirm button:first-child { color: var(--stamp-red); }
.ps-gridAddBtn { align-self: flex-start; }
.ps-gridPopoverError { color: var(--stamp-red); font-size: 11px; font-weight: 600; }

/* Touch-drag ghost */
.ps-touchDragGhost { position: fixed; left: 0; top: 0; z-index: 10002; transform: translate(-50%, -120%); pointer-events: none;
  max-width: 220px; font-family: var(--font-ps-mono), monospace; font-size: 11px; font-weight: 600; color: var(--ink);
  background: var(--paper); border: 1px solid var(--brand); border-radius: 8px; padding: 6px 10px;
  box-shadow: 0 10px 24px -8px rgba(0, 0, 0, 0.4); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Highlight picker trigger */
.ps-highlightBtn { flex: 0 0 auto; width: 16px; height: 16px; border-radius: 50%; border: 1px solid rgba(0, 0, 0, 0.15);
  background: transparent; padding: 2px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
  vertical-align: middle; margin-right: 4px; }
:where(.ps-theme) .ps-highlightBtn { border: 1px solid rgba(0, 0, 0, 0.15); padding: 2px; }
.ps-highlightSwatch { display: block; width: 100%; height: 100%; border-radius: 50%; }

/* Load error */
.ps-loadError { background: var(--paper); border-radius: 10px; padding: 36px 24px; text-align: center; color: var(--stamp-red);
  font-weight: 700; font-size: 13px; display: flex; flex-direction: column; align-items: center; gap: 12px; }

/* ---------- Skeleton (loading.tsx) ---------- */
@keyframes psShimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.ps-skeletonBarDark { border-radius: 8px; background: linear-gradient(100deg, rgba(20, 26, 33, 0.06) 30%,
  rgba(20, 26, 33, 0.12) 50%, rgba(20, 26, 33, 0.06) 70%); background-size: 250% 100%; animation: psShimmer 1.6s ease-in-out infinite; }
.ps-skeletonBarLight { border-radius: 6px; background: linear-gradient(100deg, rgba(20, 26, 33, 0.06) 30%,
  rgba(20, 26, 33, 0.12) 50%, rgba(20, 26, 33, 0.06) 70%); background-size: 250% 100%; animation: psShimmer 1.6s ease-in-out infinite; }
.ps-skeletonMonthBar { display: flex; align-items: center; justify-content: space-between; padding: 0 4px; margin-bottom: 10px; }
.ps-skeletonDayRail { display: flex; gap: 8px; padding: 4px 4px 18px; overflow: hidden; }
.ps-skeletonDayTab { flex: 0 0 auto; width: 54px; height: 62px; }
.ps-skeletonGridShell { background: var(--paper); border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(16, 24, 40, 0.08);
  border: 1px solid var(--page-line); }
.ps-skeletonGridHeaderRow { display: flex; background: #0f2e52; padding: 12px; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
.ps-skeletonGridHeaderCell { flex: 1 0 150px; height: 34px; }
.ps-skeletonGridHeaderCorner { width: 168px; min-width: 168px; flex: 0 0 auto; height: 20px; align-self: center; }
.ps-skeletonGridRow { display: flex; align-items: center; gap: 12px; padding: 14px 12px; border-bottom: 1px solid var(--line); }
.ps-skeletonGridRow:last-child { border-bottom: none; }
.ps-skeletonGridLineCell { width: 168px; min-width: 168px; flex: 0 0 auto; height: 22px; border-radius: 999px; }
.ps-skeletonGridDayCell { flex: 1 0 150px; height: 40px; }

/* ---------- Popover / dialog / form (shadcn + Tailwind equivalents) ---------- */
@keyframes psPopIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
@keyframes psFadeIn { from { opacity: 0; } to { opacity: 1; } }
.ps-popover { position: fixed; z-index: 10000; width: 320px; max-width: calc(100vw - 2rem); overflow: auto;
  border-radius: 19px; background: #fff; padding: 16px; font-size: 14px; line-height: 20px; color: #0a0a0a;
  box-shadow: 0 0 0 1px rgba(10, 10, 10, 0.1), 0 10px 24px -12px rgba(0, 0, 0, 0.25); outline: none;
  animation: psPopIn 0.1s ease-out; }
.ps-dialogOverlay { position: fixed; inset: 0; z-index: 10001; background: rgba(0, 0, 0, 0.1);
  -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px); animation: psFadeIn 0.1s ease-out; }
.ps-dialog { position: fixed; top: 50%; left: 50%; z-index: 10001; display: grid; gap: 16px; width: 100%; max-width: calc(100% - 2rem);
  transform: translate(-50%, -50%); border-radius: 19px; background: #fff; padding: 16px; font-size: 14px; line-height: 20px; color: #0a0a0a;
  box-shadow: 0 0 0 1px rgba(10, 10, 10, 0.1); overflow: hidden; outline: none; }
@media (min-width: 640px) { .ps-dialog { max-width: 24rem; } }
.ps-dialogHeader { display: flex; flex-direction: column; gap: 8px; padding-right: 24px; }
.ps-dialogTitle { font-size: 16px; line-height: 1; font-weight: 500; }
.ps-dialogDesc { font-size: 14px; color: #737373; }
.ps-dialogFooter { margin: 0 -16px -16px; display: flex; flex-direction: column-reverse; gap: 8px; border-top: 1px solid #e5e5e5;
  background: rgba(245, 245, 245, 0.5); padding: 16px; }
@media (min-width: 640px) { .ps-dialogFooter { flex-direction: row; justify-content: flex-end; } }
.ps-dialogClose { position: absolute; top: 8px; right: 8px; width: 28px; height: 28px; display: inline-flex; align-items: center;
  justify-content: center; border-radius: 10px; color: #0a0a0a; }
.ps-dialogClose:hover { background: #f5f5f5; }
.ps-btn { display: inline-flex; align-items: center; justify-content: center; height: 32px; padding: 0 10px; border-radius: 13.6px;
  border: 1px solid transparent; font-size: 14px; font-weight: 500; white-space: nowrap; transition: all 0.15s; }
:where(.ps-theme) .ps-btn { border: 1px solid transparent; padding: 0 10px; text-align: center; }
.ps-btn:disabled { pointer-events: none; opacity: 0.5; }
.ps-btn:active { transform: translateY(1px); }
.ps-btnPrimary { background: #0a6cff; color: #fff; }
.ps-btnPrimary:hover { background: rgba(10, 108, 255, 0.8); }
:where(.ps-theme) .ps-btnOutline, .ps-btnOutline { border-color: #e5e5e5; background: #fff; color: #0a0a0a; }
.ps-btnOutline:hover { background: #f5f5f5; }

.ps-fCol { display: flex; flex-direction: column; }
.ps-gap1 { gap: 4px; } .ps-gap2 { gap: 8px; } .ps-gap3 { gap: 12px; }
.ps-fRow { display: flex; align-items: center; }
.ps-fBetween { display: flex; align-items: center; justify-content: space-between; }
.ps-fGrid { display: grid; gap: 12px; }
@media (min-width: 640px) { .ps-fGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (min-width: 1024px) { .ps-fGrid { grid-template-columns: repeat(4, minmax(0, 1fr)); } .ps-fSpan3 { grid-column: span 3 / span 3; } }
.ps-fLabel { font-size: 11px; font-weight: 600; color: #5c6672; }
.ps-fInput { width: 100%; border-radius: 11px; border: 1px solid rgba(0, 0, 0, 0.1); background: #fff; padding: 6px 10px;
  font-size: 14px; line-height: 20px; color: #141a21; outline: none; }
:where(.ps-theme) .ps-fInput { border: 1px solid rgba(0, 0, 0, 0.1); background: #fff; padding: 6px 10px; }
.ps-fInput:focus { border-color: rgba(0, 0, 0, 0.3); }
.ps-fHint { font-size: 11px; line-height: 16px; color: #5c6672; }
.ps-fError { font-size: 12px; line-height: 16px; font-weight: 600; color: #a4342a; }
.ps-fSubmit { border-radius: 11px; padding: 6px 12px; font-size: 12px; line-height: 16px; font-weight: 700; color: #fff; }
:where(.ps-theme) .ps-fSubmit { padding: 6px 12px; color: #fff; }
.ps-fSubmit:disabled { opacity: 0.5; }
.ps-fCancel { font-size: 12px; line-height: 16px; font-weight: 600; color: #5c6672; }
.ps-fHeading { font-size: 12px; line-height: 16px; font-weight: 600; color: #3d4a5c; }
.ps-fMuted { font-size: 11px; color: #5c6672; }
.ps-swatchGrid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.ps-swatchBtn { display: flex; flex-direction: column; align-items: center; gap: 4px; border-radius: 11px; border: 1px solid transparent; padding: 6px; }
:where(.ps-theme) .ps-swatchBtn { border: 1px solid transparent; padding: 6px; }
.ps-swatchBtn:hover { border-color: rgba(0, 0, 0, 0.1); }
.ps-swatchBtn.ps-on { border-color: #141a21; }
.ps-swatchBtn:disabled { opacity: 0.5; }
.ps-swatchDot { width: 24px; height: 24px; border-radius: 999px; border: 1px solid rgba(0, 0, 0, 0.1); }
.ps-swatchName { font-size: 10px; font-weight: 500; color: #3d4a5c; }
.ps-clearBtn { border-radius: 11px; border: 1px dashed #c3ccd6; padding: 6px 12px; font-size: 11px; font-weight: 600; color: #5c6672; text-align: center; }
:where(.ps-theme) .ps-clearBtn { border: 1px dashed #c3ccd6; padding: 6px 12px; text-align: center; }
.ps-clearBtn:hover:not(:disabled) { border-color: #5c6672; color: #141a21; }
.ps-clearBtn:disabled { opacity: 0.4; }
.ps-yearBtn { border-radius: 11px; border: 1px solid rgba(0, 0, 0, 0.1); padding: 4px 8px; font-size: 12px; font-weight: 600; color: #3d4a5c; }
:where(.ps-theme) .ps-yearBtn { border: 1px solid rgba(0, 0, 0, 0.1); padding: 4px 8px; }
.ps-yearBtn:hover { background: rgba(0, 0, 0, 0.05); }
.ps-yearLabel { font-size: 14px; font-weight: 700; color: #141a21; }
.ps-monthBtn { border-radius: 11px; border: 1px solid rgba(0, 0, 0, 0.1); padding: 6px 8px; text-align: center; font-size: 12px; font-weight: 600; color: #3d4a5c; }
:where(.ps-theme) .ps-monthBtn { border: 1px solid rgba(0, 0, 0, 0.1); padding: 6px 8px; text-align: center; }
.ps-monthBtn:hover { background: rgba(0, 0, 0, 0.05); }
.ps-monthBtn.ps-on { border-color: #0b489d; background: #0b489d; color: #fff; }

/* ---------- Mobile ---------- */
@media (max-width: 760px) {
  .ps-monthBar { flex-wrap: wrap; row-gap: 8px; }
  .ps-dayTab { width: 46px; }
  .ps-weekTab { min-width: 92px; padding: 8px 9px 9px; }
  .ps-panelHead { flex-wrap: wrap; row-gap: 8px; }
  .ps-bigDate { font-size: 20px; }
  .ps-colNote { display: none; }
  .ps-productNameTxt { max-width: 140px; }
  .ps-gridCorner, .ps-gridLineCell { width: 128px; min-width: 128px; }
  .ps-gridCorner { left: auto; }
  .ps-gridLineCell { position: static; }
  .ps-gridDayHeader, .ps-gridCell { min-width: 150px; }
  .ps-gridLinePill { font-size: 11px; padding: 3px 8px 3px 5px; }
  .ps-gridLineSubtitle { font-size: 9.5px; }
  .ps-opBtn, :where(.ps-theme) .ps-opBtn { padding: 6px 8px; font-size: 12px; }
  .ps-gridChipDelete, :where(.ps-theme) .ps-gridChipDelete { padding: 6px; font-size: 15px; }
  .ps-highlightBtn { width: 22px; height: 22px; }
}
`;

    let stylesInjected = false;
    function ensureStyles(loadFonts) {
        if (stylesInjected || typeof document === "undefined") return;
        stylesInjected = true;
        if (loadFonts && !document.querySelector(`link[href="${FONT_HREF}"]`)) {
            const preconnect = document.createElement("link");
            preconnect.rel = "preconnect";
            preconnect.href = "https://fonts.googleapis.com";
            document.head.append(preconnect);
            const link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = FONT_HREF;
            document.head.append(link);
        }
        const style = document.createElement("style");
        style.id = "production-schedule-styles";
        style.textContent = CSS;
        document.head.append(style);
    }

    /* =========================================================================
     * 10. POPOVER + DIALOG (Radix/shadcn equivalents)
     * ========================================================================= */

    function createPopoverManager() {
        let current = null;

        function reposition() {
            if (!current) return;
            const { trigger, el } = current;
            if (!trigger.isConnected) return;
            const rect = trigger.getBoundingClientRect();
            const viewportWidth = document.documentElement.clientWidth;
            const viewportHeight = window.innerHeight;
            el.style.maxHeight = "";
            const width = el.offsetWidth;
            const height = el.offsetHeight;
            let left = rect.left + rect.width / 2 - width / 2;
            left = Math.max(8, Math.min(left, viewportWidth - width - 8));
            const below = viewportHeight - rect.bottom - 12;
            const above = rect.top - 12;
            let top;
            if (height <= below || below >= above) {
                top = rect.bottom + 4;
                if (height > below)
                    el.style.maxHeight = `${Math.max(160, below)}px`;
            } else {
                const usable = Math.min(height, above);
                top = rect.top - 4 - usable;
                if (height > above) el.style.maxHeight = `${above}px`;
            }
            el.style.left = `${left}px`;
            el.style.top = `${top}px`;
        }

        function onPointerDown(event) {
            if (!current) return;
            if (
                current.el.contains(event.target) ||
                current.trigger.contains(event.target)
            )
                return;
            close();
        }
        function onKeyDown(event) {
            if (event.key === "Escape" && current) close();
        }
        document.addEventListener("pointerdown", onPointerDown, true);
        document.addEventListener("keydown", onKeyDown);
        window.addEventListener("resize", reposition);
        window.addEventListener("scroll", reposition, true);

        function open(config) {
            close();
            const el = h("div", {
                class: "ps-theme ps-popover",
                role: "dialog",
                tabindex: "-1",
            });
            if (config.width) el.style.width = `${config.width}px`;
            current = Object.assign({}, config, { el });
            const api = { close: () => close(), reposition, el };
            el.append(config.build(api));
            document.body.append(el);
            config.trigger.setAttribute("aria-expanded", "true");
            reposition();
            if (config.onOpen) config.onOpen(api);
        }

        function close() {
            if (!current) return;
            const closing = current;
            current = null;
            closing.el.remove();
            closing.trigger.setAttribute("aria-expanded", "false");
            if (closing.onClose) closing.onClose();
        }

        /** Removes the open popover WITHOUT running onClose (used across a
         * re-render so it can be re-opened on the new trigger, keeping drafts). */
        function detach() {
            if (!current) return null;
            const key = current.key;
            current.el.remove();
            current = null;
            return key;
        }

        function isOpen(key) {
            return Boolean(current && current.key === key);
        }

        function destroy() {
            detach();
            document.removeEventListener("pointerdown", onPointerDown, true);
            document.removeEventListener("keydown", onKeyDown);
            window.removeEventListener("resize", reposition);
            window.removeEventListener("scroll", reposition, true);
        }

        return { open, close, detach, isOpen, reposition, destroy };
    }

    function openDialog({ title, description, actions, onCancel }) {
        const buttons = actions.map((action) =>
            h(
                "button",
                {
                    type: "button",
                    class: cx(
                        "ps-btn",
                        action.variant === "outline"
                            ? "ps-btnOutline"
                            : "ps-btnPrimary",
                    ),
                    onClick: action.onClick,
                    disabled: action.disabled,
                },
                action.label,
            ),
        );
        const overlay = h("div", {
            class: "ps-theme ps-dialogOverlay",
            onClick: () => onCancel(),
        });
        const closeBtn = h("button", {
            type: "button",
            class: "ps-dialogClose",
            html: X_ICON,
            "aria-label": "Close",
        });
        closeBtn.addEventListener("click", () => onCancel());
        const content = h(
            "div",
            {
                class: "ps-theme ps-dialog",
                role: "dialog",
                "aria-modal": "true",
                tabindex: "-1",
            },
            h(
                "div",
                { class: "ps-dialogHeader" },
                h("h2", { class: "ps-dialogTitle", style: "margin:0" }, title),
                h("p", { class: "ps-dialogDesc" }, description),
            ),
            h("div", { class: "ps-dialogFooter" }, buttons),
            closeBtn,
        );
        const onKey = (event) => {
            if (event.key === "Escape") onCancel();
        };
        document.addEventListener("keydown", onKey);
        document.body.append(overlay, content);
        content.focus();
        return {
            close() {
                document.removeEventListener("keydown", onKey);
                overlay.remove();
                content.remove();
            },
            setDisabled(disabled) {
                for (const button of buttons) button.disabled = disabled;
            },
        };
    }

    /* =========================================================================
     * 11. DRAG ENCODING (schedule-grid-board.tsx)
     * ========================================================================= */

    // Browsers lowercase drag MIME types, so every part must be lowercase ASCII:
    // the item id is hex-encoded, the line is its index, the date is digits.
    const DRAG_TYPE_PREFIX = "application/x-schedule-item";
    const DRAG_TYPE_PATTERN = new RegExp(
        `^${escapeRegExp(DRAG_TYPE_PREFIX)};item=([0-9a-f]+);line=(\\d+);date=(\\d{4}-\\d{2}-\\d{2})$`,
    );
    const LONG_PRESS_MS = 350;
    const MOVE_CANCEL_PX = 12;

    function hexEncode(text) {
        return Array.from(new TextEncoder().encode(text), (byte) =>
            byte.toString(16).padStart(2, "0"),
        ).join("");
    }
    function hexDecode(hex) {
        const bytes = new Uint8Array(
            hex.match(/../g).map((pair) => parseInt(pair, 16)),
        );
        return new TextDecoder().decode(bytes);
    }
    function buildDragType(itemId, lineIndex, dateKey) {
        return `${DRAG_TYPE_PREFIX};item=${hexEncode(itemId)};line=${lineIndex};date=${dateKey}`;
    }
    function parseDragType(types) {
        for (const type of Array.from(types || [])) {
            const match = DRAG_TYPE_PATTERN.exec(type);
            if (!match) continue;
            return {
                itemId: hexDecode(match[1]),
                sourceLineIndex: Number(match[2]),
                sourceDate: match[3],
            };
        }
        return null;
    }

    /* =========================================================================
     * 12. MOUNT — the page + every component
     * ========================================================================= */

    function resolveOptions(input) {
        const options = Object.assign({}, input);
        options.text = Object.assign({}, DEFAULT_TEXT, input.text || {});
        options.permissions = Object.assign(
            { canCreate: true, canEdit: true, canDelete: true },
            input.permissions || {},
        );
        options.lines = (input.lines || PRODUCTION_LINES).map((line) =>
            Object.assign({}, line),
        );
        options.highlightColors = input.highlightColors || HIGHLIGHT_COLORS;
        options.urlParams = Object.assign(
            { date: "date", view: "view" },
            input.urlParams || {},
        );
        options.loadFonts = input.loadFonts !== false;
        options.dataSource = input.dataSource;
        if (!options.dataSource)
            throw new Error("[ProductionSchedule] dataSource is required");
        for (const method of ["list", "create", "update", "remove"]) {
            if (typeof options.dataSource[method] !== "function") {
                throw new Error(
                    `[ProductionSchedule] dataSource.${method}() is required`,
                );
            }
        }
        const calendar = input.calendar;
        if (!calendar) options.loadCalendar = async () => ({});
        else if (typeof calendar === "function")
            options.loadCalendar = calendar;
        else if (typeof calendar.getDayTypes === "function")
            options.loadCalendar = (range) => calendar.getDayTypes(range);
        else options.loadCalendar = staticCalendar(calendar);
        return options;
    }

    function mount(target, userOptions) {
        const host =
            typeof target === "string"
                ? document.querySelector(target)
                : target;
        if (!host)
            throw new Error("[ProductionSchedule] mount target not found");

        const opts = resolveOptions(userOptions || {});
        const text = opts.text;
        const lines = opts.lines;
        const fmt = createOrderNumberFormatter(opts.orderNumber);
        ensureStyles(opts.loadFonts);

        const getLine = (key) => lines.find((line) => line.key === key);
        const getHighlight = (key) =>
            key
                ? opts.highlightColors.find((color) => color.key === key)
                : undefined;
        const isTouchCapable =
            typeof navigator !== "undefined" && navigator.maxTouchPoints > 0;

        const pop = createPopoverManager();
        const root = h("div", { class: "ps-root" });
        host.append(root);

        // Shared touch-drag ghost and current drop-target cell (refs, not state).
        const ghost = h("div", {
            class: "ps-theme ps-touchDragGhost",
            style: { display: "none" },
        });
        document.body.append(ghost);
        let dragOverCell = null;

        const initialUrl = opts.syncUrl ? readUrl() : {};
        const state = {
            anchor: parseAnchor(initialUrl.date || opts.initialDate),
            view: parseScheduleView(initialUrl.view || opts.initialView),
            items: [],
            explicitTypes: new Map(),
            loading: true,
            loadError: null,
            pendingCopyIds: new Set(),
            moveError: null,
            addingLine: null,
            editingIds: new Set(),
            drafts: new Map(),
            inflight: 0,
            destroyed: false,
        };
        let loadToken = 0;
        let activeDialog = null;

        /* ---------------- URL sync ---------------- */

        function readUrl() {
            try {
                const params = new URLSearchParams(window.location.search);
                return {
                    date: params.get(opts.urlParams.date) || undefined,
                    view: params.get(opts.urlParams.view) || undefined,
                };
            } catch {
                return {};
            }
        }
        function writeUrl(push) {
            try {
                const url = new URL(window.location.href);
                url.searchParams.set(
                    opts.urlParams.date,
                    dateParam(state.anchor),
                );
                url.searchParams.set(opts.urlParams.view, state.view);
                window.history[push ? "pushState" : "replaceState"](
                    window.history.state,
                    "",
                    url,
                );
            } catch {
                /* ignore (sandboxed iframes etc.) */
            }
        }
        function onPopState() {
            const next = readUrl();
            navigate(next.date, next.view, { push: false });
        }
        if (opts.syncUrl) {
            window.addEventListener("popstate", onPopState);
            writeUrl(false);
        }

        /* ---------------- data loading ---------------- */

        function neededRange() {
            const monthDays = buildMonthDays(state.anchor);
            let from = monthDays[0];
            let to = monthDays[monthDays.length - 1];
            if (state.view === "week") {
                const week = buildWeekGrid(state.anchor);
                if (week[0] < from) from = week[0];
                if (week[6] > to) to = week[6];
            }
            return { from: dateParam(from), to: dateParam(to) };
        }

        async function load({ silent = false } = {}) {
            const token = ++loadToken;
            const range = neededRange();
            if (!silent) {
                state.loading = true;
                render();
            }
            try {
                const [rows, types] = await Promise.all([
                    opts.dataSource.list(range),
                    Promise.resolve(opts.loadCalendar(range)).catch((cause) => {
                        console.error(
                            "[ProductionSchedule] calendar load failed",
                            cause,
                        );
                        return {};
                    }),
                ]);
                if (token !== loadToken || state.destroyed) return;
                state.items = (rows || [])
                    .map(normalizeItem)
                    .sort(compareItems);
                const typeMap = new Map();
                const entries =
                    types instanceof Map
                        ? Array.from(types.entries())
                        : Object.entries(types || {});
                for (const [key, value] of entries) {
                    const type = normalizeDayType(value);
                    if (type) typeMap.set(toDateKey(key), type);
                }
                state.explicitTypes = typeMap;
                state.pendingCopyIds = new Set();
                state.loadError = null;
            } catch (cause) {
                if (token !== loadToken || state.destroyed) return;
                if (!silent)
                    state.loadError = errorMessage(cause, text.loadFailed);
                else
                    console.error("[ProductionSchedule] refresh failed", cause);
            }
            state.loading = false;
            render();
        }

        const refresh = () => load({ silent: true });

        function navigate(dateKey, view, { push = true } = {}) {
            state.anchor = parseAnchor(dateKey);
            state.view = parseScheduleView(view);
            state.addingLine = null;
            state.editingIds.clear();
            state.drafts.clear();
            state.moveError = null;
            if (opts.syncUrl && push) writeUrl(true);
            if (opts.onNavigate)
                opts.onNavigate({
                    date: dateParam(state.anchor),
                    view: state.view,
                });
            load();
        }

        function emit(event) {
            if (opts.onChange) {
                try {
                    opts.onChange(event);
                } catch (cause) {
                    console.error(cause);
                }
            }
        }

        /* ---------------- service layer (actions.ts + service.ts) ---------------- */

        const DENIED = { ok: false, error: "この操作を行う権限がありません。" };
        const validDateKey = (key) =>
            typeof key === "string" && dateParam(parseAnchor(key)) === key;
        const findItem = (id) => state.items.find((item) => item.id === id);

        const service = {
            async saveItem(input) {
                const parsed = validateItemInput(input, lines, fmt);
                if (!parsed.ok) return parsed;
                const data = parsed.value;
                if (!opts.permissions[data.id ? "canEdit" : "canCreate"])
                    return DENIED;
                if (!validDateKey(data.date))
                    return {
                        ok: false,
                        error: "日付の形式が正しくありません。",
                    };
                if (!getLine(data.line))
                    return { ok: false, error: "そのラインは存在しません。" };

                let personInitial;
                let orderNumber;
                let productName;
                let quantity;
                if (data.orderNumberInput) {
                    const formatted = fmt.format(data.orderNumberInput);
                    if (!formatted)
                        return {
                            ok: false,
                            error: "注番は数字のみで入力してください。",
                        };
                    if (typeof opts.orderLookup !== "function")
                        return { ok: false, error: text.lookupNotConfigured };
                    let match;
                    try {
                        match = await opts.orderLookup(formatted);
                    } catch (cause) {
                        return {
                            ok: false,
                            error: errorMessage(cause, text.lookupFailed),
                        };
                    }
                    if (!match)
                        return {
                            ok: false,
                            error: text.lookupNotFound.replace(
                                "{orderNumber}",
                                formatted,
                            ),
                        };
                    orderNumber = formatted;
                    personInitial = String(match.personInitial || "");
                    productName = String(match.productName || "");
                    quantity = String(
                        match.quantity == null ? "" : match.quantity,
                    );
                } else {
                    orderNumber = "";
                    personInitial = data.personInitial;
                    productName = data.productName;
                    quantity = data.quantity;
                }

                const record = {
                    date: data.date,
                    line: data.line,
                    personInitial,
                    orderNumber,
                    productName,
                    quantity,
                    notes: data.notes,
                };
                try {
                    if (data.id) {
                        const existing = findItem(data.id);
                        const updated = await opts.dataSource.update(
                            data.id,
                            record,
                        );
                        const item = normalizeItem(
                            updated ||
                                Object.assign({}, existing, record, {
                                    id: data.id,
                                }),
                        );
                        emit({ type: "update", item });
                        return { ok: true, item };
                    }
                    const created = await opts.dataSource.create(
                        Object.assign({}, record, { highlightColor: null }),
                    );
                    const item = normalizeItem(created);
                    emit({ type: "create", item });
                    return { ok: true, item };
                } catch (cause) {
                    return {
                        ok: false,
                        error: errorMessage(
                            cause,
                            "この予定を保存できませんでした。",
                        ),
                    };
                }
            },

            async deleteItem(itemId) {
                if (!opts.permissions.canDelete) return DENIED;
                try {
                    await opts.dataSource.remove(itemId);
                    emit({ type: "delete", itemId });
                    return { ok: true };
                } catch (cause) {
                    return {
                        ok: false,
                        error: errorMessage(
                            cause,
                            "この予定を削除できませんでした。",
                        ),
                    };
                }
            },

            async setHighlight(itemId, color) {
                if (!opts.permissions.canEdit) return DENIED;
                if (color !== null && !getHighlight(color))
                    return { ok: false, error: "無効な色です。" };
                try {
                    await opts.dataSource.update(itemId, {
                        highlightColor: color,
                    });
                    emit({ type: "highlight", itemId, color });
                    return { ok: true };
                } catch (cause) {
                    return {
                        ok: false,
                        error: errorMessage(
                            cause,
                            "ハイライトを更新できませんでした。",
                        ),
                    };
                }
            },

            async moveItem(itemId, date, line) {
                if (!opts.permissions.canEdit) return DENIED;
                if (!validDateKey(date))
                    return {
                        ok: false,
                        error: "日付の形式が正しくありません。",
                    };
                if (line !== undefined && !getLine(line))
                    return { ok: false, error: "そのラインは存在しません。" };
                try {
                    await opts.dataSource.update(
                        itemId,
                        line !== undefined ? { date, line } : { date },
                    );
                    emit({ type: "move", itemId, date, line });
                    return { ok: true };
                } catch (cause) {
                    return {
                        ok: false,
                        error: errorMessage(
                            cause,
                            "この予定を移動できませんでした。",
                        ),
                    };
                }
            },

            async copyItem(source, date, line) {
                if (!opts.permissions.canCreate) return DENIED;
                if (!validDateKey(date))
                    return {
                        ok: false,
                        error: "日付の形式が正しくありません。",
                    };
                if (!getLine(line))
                    return { ok: false, error: "そのラインは存在しません。" };
                try {
                    // the server copies the schedule as it is (copy); otherwise a new one with its values
                    const created =
                        typeof opts.dataSource.copy === "function"
                            ? await opts.dataSource.copy(source.id, date, line)
                            : await opts.dataSource.create({
                                  date,
                                  line,
                                  personInitial: source.personInitial,
                                  orderNumber: source.orderNumber,
                                  productName: source.productName,
                                  quantity: source.quantity,
                                  notes: source.notes.slice(),
                                  highlightColor: source.highlightColor,
                              });
                    const item = normalizeItem(created);
                    emit({ type: "copy", sourceId: source.id, item });
                    return { ok: true, item };
                } catch (cause) {
                    return {
                        ok: false,
                        error: errorMessage(
                            cause,
                            "この予定をコピーできませんでした。",
                        ),
                    };
                }
            },
        };

        /* ---------------- render ---------------- */

        function render() {
            if (state.destroyed) return;
            const reopenKey = pop.detach();
            root.replaceChildren(state.loading ? buildSkeleton() : buildPage());
            if (reopenKey) {
                const trigger = Array.from(
                    root.querySelectorAll("[data-pop-key]"),
                ).find((el) => el.dataset.popKey === reopenKey);
                if (trigger && trigger.__psOpen) trigger.__psOpen(true);
            }
        }

        /** Wires `trigger` to toggle a popover; re-openable across re-renders. */
        function bindPopover(trigger, config) {
            trigger.dataset.popKey = config.key;
            trigger.setAttribute("aria-haspopup", "dialog");
            trigger.setAttribute("aria-expanded", "false");
            const openIt = (restored) =>
                pop.open({
                    key: config.key,
                    trigger,
                    width: config.width,
                    build: (api) => config.build(api, restored === true),
                    onClose: config.onClose,
                });
            trigger.__psOpen = openIt;
            trigger.addEventListener("click", () => {
                if (config.enabled && !config.enabled()) return;
                if (pop.isOpen(config.key)) pop.close();
                else openIt(false);
            });
            return trigger;
        }

        function buildSkeleton() {
            const range = (count, fn) =>
                Array.from({ length: count }, (_, index) => fn(index));
            return h(
                "div",
                {
                    class: "ps-theme ps-wrap",
                    "aria-busy": "true",
                    "aria-label": `${text.title}を読み込み中`,
                },
                h(
                    "div",
                    { class: "ps-skeletonMonthBar" },
                    h("div", { class: "ps-skeletonBarDark ps-monthNavArrow" }),
                    h("div", {
                        class: "ps-skeletonBarDark",
                        style: { width: "140px", height: "22px" },
                    }),
                    h("div", {
                        class: "ps-skeletonBarDark",
                        style: {
                            width: "180px",
                            height: "26px",
                            borderRadius: "999px",
                        },
                    }),
                ),
                state.view === "month"
                    ? null
                    : h(
                          "div",
                          { class: "ps-skeletonDayRail" },
                          range(7, () =>
                              h("div", {
                                  class: "ps-skeletonBarDark ps-skeletonDayTab",
                              }),
                          ),
                      ),
                h(
                    "div",
                    { class: "ps-skeletonGridShell" },
                    h(
                        "div",
                        { class: "ps-skeletonGridHeaderRow" },
                        h("div", {
                            class: "ps-skeletonBarDark ps-skeletonGridHeaderCorner",
                        }),
                        range(4, () =>
                            h("div", {
                                class: "ps-skeletonBarDark ps-skeletonGridHeaderCell",
                            }),
                        ),
                    ),
                    range(6, () =>
                        h(
                            "div",
                            { class: "ps-skeletonGridRow" },
                            h("div", {
                                class: "ps-skeletonBarLight ps-skeletonGridLineCell",
                            }),
                            range(4, () =>
                                h("div", {
                                    class: "ps-skeletonBarLight ps-skeletonGridDayCell",
                                }),
                            ),
                        ),
                    ),
                ),
            );
        }

        function buildPage() {
            const anchor = state.anchor;
            const dateKey = dateParam(anchor);
            const view = state.view;
            const todayKey = dateParam(new Date());
            const monthDaysAll = buildMonthDays(anchor);
            const monthDayTypes = buildEffectiveDayTypeMap(
                monthDaysAll,
                state.explicitTypes,
            );
            const monthPrefix = dateKey.slice(0, 7);
            const dataDays = new Set(
                state.items
                    .filter((item) => item.date.slice(0, 7) === monthPrefix)
                    .map((item) => Number(item.date.slice(8, 10))),
            );

            let weekDays = [];
            let weekRangeLabel = "";
            if (view === "week") {
                weekDays = buildWeekGrid(anchor);
                weekRangeLabel = `${weekDays[0].getMonth() + 1}/${weekDays[0].getDate()} 〜 ${weekDays[6].getMonth() + 1}/${weekDays[6].getDate()}`;
            }

            let body;
            if (state.loadError) {
                body = h(
                    "div",
                    { class: "ps-loadError" },
                    state.loadError,
                    h(
                        "button",
                        {
                            type: "button",
                            class: "ps-btn ps-btnOutline",
                            onClick: () => load(),
                        },
                        "再読み込み",
                    ),
                );
            } else if (view === "day") {
                body = dayPanel(anchor, dateKey, todayKey, monthDayTypes);
            } else {
                body = gridBoard(
                    view === "week" ? weekDays : monthDaysAll,
                    todayKey,
                );
            }

            return h(
                "div",
                { class: "ps-theme ps-wrap" },
                monthBar(anchor, dateKey, view, weekRangeLabel),
                view === "day"
                    ? dayRail(
                          anchor,
                          dateKey,
                          todayKey,
                          dataDays,
                          monthDayTypes,
                      )
                    : null,
                view === "week"
                    ? weekRail(anchor, todayKey, dataDays, monthDayTypes)
                    : null,
                body,
            );
        }

        /* ---------------- month bar ---------------- */

        function monthBar(anchor, dateKey, view, weekRangeLabel) {
            const monthLabel = new Intl.DateTimeFormat("ja-JP", {
                year: "numeric",
                month: "long",
            }).format(anchor);
            const shift = (direction) =>
                dateParam(
                    new Date(
                        anchor.getFullYear(),
                        anchor.getMonth() + direction,
                        1,
                    ),
                );
            const toggle = (key, label, title) =>
                h(
                    "button",
                    {
                        type: "button",
                        class: cx(
                            "ps-viewToggleBtn",
                            view === key && "ps-active",
                        ),
                        title,
                        onClick: () => navigate(dateKey, key),
                    },
                    label,
                );

            return h(
                "div",
                { class: "ps-monthBar" },
                h(
                    "div",
                    { class: "ps-monthNavGroup" },
                    h(
                        "button",
                        {
                            type: "button",
                            class: "ps-monthNavArrow",
                            "aria-label": "前の月",
                            title: "前の月へ移動",
                            onClick: () => navigate(shift(-1), view),
                        },
                        "‹",
                    ),
                    monthYearPicker(monthLabel, anchor, dateKey, view),
                    h(
                        "button",
                        {
                            type: "button",
                            class: "ps-monthNavArrow",
                            "aria-label": "次の月",
                            title: "次の月へ移動",
                            onClick: () => navigate(shift(1), view),
                        },
                        "›",
                    ),
                ),
                h(
                    "div",
                    { class: "ps-monthRight" },
                    view === "week"
                        ? h("span", { class: "ps-total" }, weekRangeLabel)
                        : null,
                    h(
                        "div",
                        { class: "ps-viewToggle" },
                        toggle("day", "1日", "1日分の予定を1つの表で表示"),
                        toggle(
                            "week",
                            "7日",
                            "1週間（7日分）の予定をラインごとに一覧表示",
                        ),
                        toggle(
                            "month",
                            "30日",
                            "選択中の月のすべての予定をラインごとに一覧表示",
                        ),
                    ),
                ),
            );
        }

        function monthYearPicker(monthLabel, anchor, dateKey, view) {
            const trigger = h(
                "button",
                {
                    type: "button",
                    class: "ps-monthTitle ps-monthTitleBtn",
                    "aria-label": `${dateKey}から月を選択`,
                    title: "クリックして年・月を指定して移動",
                },
                monthLabel,
                h("span", { class: "ps-monthTitleCaret" }, "▾"),
            );
            return bindPopover(trigger, {
                key: "month-picker",
                width: 256,
                build: (api) => {
                    let browsingYear = anchor.getFullYear();
                    const box = h("div", { class: "ps-fCol ps-gap3" });
                    const paint = () => {
                        box.replaceChildren(
                            h(
                                "div",
                                { class: "ps-fBetween" },
                                h(
                                    "button",
                                    {
                                        type: "button",
                                        class: "ps-yearBtn",
                                        "aria-label": "前の年",
                                        title: "前の年を表示",
                                        onClick: () => {
                                            browsingYear -= 1;
                                            paint();
                                        },
                                    },
                                    "‹",
                                ),
                                h(
                                    "span",
                                    { class: "ps-yearLabel" },
                                    `${browsingYear}年`,
                                ),
                                h(
                                    "button",
                                    {
                                        type: "button",
                                        class: "ps-yearBtn",
                                        "aria-label": "次の年",
                                        title: "次の年を表示",
                                        onClick: () => {
                                            browsingYear += 1;
                                            paint();
                                        },
                                    },
                                    "›",
                                ),
                            ),
                            h(
                                "div",
                                { class: "ps-swatchGrid" },
                                MONTHS_JP.map((label, month) =>
                                    h(
                                        "button",
                                        {
                                            type: "button",
                                            class: cx(
                                                "ps-monthBtn",
                                                browsingYear ===
                                                    anchor.getFullYear() &&
                                                    month ===
                                                        anchor.getMonth() &&
                                                    "ps-on",
                                            ),
                                            title: `${browsingYear}年${label}の予定表へ移動`,
                                            onClick: () => {
                                                api.close();
                                                navigate(
                                                    dateParam(
                                                        new Date(
                                                            browsingYear,
                                                            month,
                                                            1,
                                                        ),
                                                    ),
                                                    view,
                                                );
                                            },
                                        },
                                        label,
                                    ),
                                ),
                            ),
                        );
                    };
                    paint();
                    return box;
                },
            });
        }

        /* ---------------- rails ---------------- */

        function dayRail(anchor, dateKey, todayKey, dataDays, monthDayTypes) {
            const total = daysInMonth(anchor);
            return h(
                "div",
                { class: "ps-dayRail" },
                Array.from({ length: total }, (_, index) => index + 1).map(
                    (day) => {
                        const date = new Date(
                            anchor.getFullYear(),
                            anchor.getMonth(),
                            day,
                        );
                        const key = dateParam(date);
                        const weekday = date.getDay();
                        const isWeekend = weekday === 0 || weekday === 6;
                        const hasData = dataDays.has(day);
                        const type = monthDayTypes.get(key);
                        const isDayHoliday = isFullyClosed(type);
                        const isDayHalfDay = isHalfDay(type);
                        const title = [
                            `${anchor.getMonth() + 1}月${day}日（${DOW_JP[weekday]}）の予定を表示`,
                            isDayHoliday
                                ? "休日"
                                : isDayHalfDay
                                  ? "半日出勤日"
                                  : null,
                            hasData ? "予定あり" : null,
                            key === todayKey ? "本日" : null,
                        ]
                            .filter(Boolean)
                            .join(" ・ ");
                        return h(
                            "button",
                            {
                                type: "button",
                                class: cx(
                                    "ps-dayTab",
                                    hasData && "ps-hasData",
                                    isWeekend && "ps-isWeekend",
                                    isDayHoliday && "ps-isHoliday",
                                    isDayHalfDay && "ps-isHalfDay",
                                    key === todayKey && "ps-isToday",
                                    key === dateKey && "ps-active",
                                ),
                                title,
                                onClick: () => navigate(key, "day"),
                            },
                            h("span", { class: "ps-dow" }, DOW_JP[weekday]),
                            h("span", { class: "ps-num" }, String(day)),
                            h("span", { class: "ps-pip" }),
                        );
                    },
                ),
            );
        }

        function weekRail(anchor, todayKey, dataDays, monthDayTypes) {
            const activeMonday = dateParam(startOfWeek(anchor));
            return h(
                "div",
                { class: "ps-dayRail" },
                buildMonthWeekRows(anchor).map((row, index) => {
                    const monday = row[0];
                    const sunday = row[6];
                    const mondayKey = dateParam(monday);
                    const isCurrentWeek = row.some(
                        (day) => dateParam(day) === todayKey,
                    );
                    const hasData = row.some(
                        (day) =>
                            day.getMonth() === anchor.getMonth() &&
                            dataDays.has(day.getDate()),
                    );
                    const isWeekHoliday = row.every(
                        (day) =>
                            day.getMonth() === anchor.getMonth() &&
                            isFullyClosed(monthDayTypes.get(dateParam(day))),
                    );
                    const title = [
                        `第${index + 1}週（${monday.getMonth() + 1}/${monday.getDate()}〜${sunday.getMonth() + 1}/${sunday.getDate()}）の予定を表示`,
                        isWeekHoliday ? "全日休日" : null,
                        hasData ? "予定あり" : null,
                        isCurrentWeek ? "今週" : null,
                    ]
                        .filter(Boolean)
                        .join(" ・ ");
                    return h(
                        "button",
                        {
                            type: "button",
                            class: cx(
                                "ps-weekTab",
                                hasData && "ps-hasData",
                                isWeekHoliday && "ps-isHoliday",
                                isCurrentWeek && "ps-isToday",
                                mondayKey === activeMonday && "ps-active",
                            ),
                            title,
                            onClick: () => navigate(mondayKey, "week"),
                        },
                        h(
                            "span",
                            { class: "ps-weekLabel" },
                            `第${index + 1}週`,
                        ),
                        h(
                            "span",
                            { class: "ps-weekRange" },
                            `${monday.getMonth() + 1}/${monday.getDate()}〜${
                                sunday.getMonth() !== monday.getMonth()
                                    ? `${sunday.getMonth() + 1}/`
                                    : ""
                            }${sunday.getDate()}`,
                        ),
                        h("span", { class: "ps-pip" }),
                    );
                }),
            );
        }

        /* ---------------- shared bits ---------------- */

        function linePill(line, prefix) {
            return h(
                "span",
                {
                    class: `ps-${prefix}Pill`,
                    style: { background: `${line.color}22`, color: line.color },
                },
                h(
                    "span",
                    {
                        class: `ps-${prefix}Ic`,
                        style: { background: line.color },
                    },
                    "機",
                ),
                line.label,
            );
        }

        function highlightPicker(item) {
            const current = getHighlight(item.highlightColor);
            const trigger = h(
                "button",
                {
                    type: "button",
                    class: "ps-highlightBtn",
                    title: current
                        ? `クリックして背景ハイライトを変更（現在: ${current.label}）`
                        : "クリックしてこの予定の背景を色で強調表示",
                    "aria-label": "ハイライト色を選択",
                },
                h("span", {
                    class: "ps-highlightSwatch",
                    style: {
                        background: current ? current.swatch : "transparent",
                    },
                }),
            );
            return bindPopover(trigger, {
                key: `hl|${item.id}`,
                width: 256,
                build: (api) => {
                    const errorEl = h("p", {
                        class: "ps-fError",
                        style: { display: "none" },
                    });
                    const buttons = [];
                    const choose = async (color) => {
                        for (const button of buttons) button.disabled = true;
                        errorEl.style.display = "none";
                        const result = await service.setHighlight(
                            item.id,
                            color,
                        );
                        if (!result.ok) {
                            for (const button of buttons)
                                button.disabled = false;
                            clearBtn.disabled = item.highlightColor === null;
                            errorEl.textContent = result.error;
                            errorEl.style.display = "";
                            return;
                        }
                        api.close();
                        refresh();
                    };
                    for (const option of opts.highlightColors) {
                        const isActive = item.highlightColor === option.key;
                        buttons.push(
                            h(
                                "button",
                                {
                                    type: "button",
                                    class: cx(
                                        "ps-swatchBtn",
                                        isActive && "ps-on",
                                    ),
                                    "aria-pressed": isActive ? "true" : "false",
                                    title: `背景を${option.label}でハイライト`,
                                    onClick: () => choose(option.key),
                                },
                                h("span", {
                                    class: "ps-swatchDot",
                                    style: { background: option.swatch },
                                }),
                                h(
                                    "span",
                                    { class: "ps-swatchName" },
                                    option.label,
                                ),
                            ),
                        );
                    }
                    const clearBtn = h(
                        "button",
                        {
                            type: "button",
                            class: "ps-clearBtn",
                            disabled: item.highlightColor === null,
                            title: "背景ハイライトを解除し、ラインの通常の色に戻す",
                            onClick: () => choose(null),
                        },
                        "デフォルトに戻す（ハイライトなし）",
                    );
                    buttons.push(clearBtn);
                    return h(
                        "div",
                        { class: "ps-fCol ps-gap3" },
                        h(
                            "div",
                            { class: "ps-fBetween" },
                            h(
                                "span",
                                { class: "ps-fHeading" },
                                "背景ハイライト",
                            ),
                            h(
                                "span",
                                { class: "ps-fMuted" },
                                current ? `現在: ${current.label}` : "未設定",
                            ),
                        ),
                        h(
                            "div",
                            { class: "ps-swatchGrid" },
                            buttons.slice(0, -1),
                        ),
                        clearBtn,
                        errorEl,
                    );
                },
            });
        }

        /** The add/edit form shared by the day table and every grid popover. */
        let uidCounter = 0;
        function itemForm({
            item,
            line,
            dateKey,
            draftKey,
            restored,
            onCancel,
            onSaved,
        }) {
            let draft = state.drafts.get(draftKey);
            const isRestored = Boolean(draft) && restored !== false;
            if (!draft) {
                draft = {
                    orderNumberInput: item ? fmt.strip(item.orderNumber) : "",
                    personInitial: item ? item.personInitial : "",
                    productName: item ? item.productName : "",
                    quantity: item ? item.quantity : "",
                    notes: item ? item.notes.join("、") : "",
                };
                state.drafts.set(draftKey, draft);
            }
            const uid = `ps-f${(uidCounter += 1)}`;
            let pending = false;

            const field = (name, id, attrs) => {
                const input = h(
                    "input",
                    Object.assign(
                        { class: "ps-fInput", id, value: draft[name] },
                        attrs || {},
                    ),
                );
                input.addEventListener("input", () => {
                    draft[name] = input.value;
                    if (name === "notes")
                        for (const other of notesInputs)
                            if (other !== input) other.value = input.value;
                    sync();
                });
                return input;
            };
            const labelled = (id, label, input, extraClass) =>
                h(
                    "div",
                    { class: cx("ps-fCol ps-gap1", extraClass) },
                    h("label", { class: "ps-fLabel", for: id }, label),
                    input,
                );

            const orderInput = field("orderNumberInput", `${uid}-order`, {
                placeholder: "123",
                inputmode: "numeric",
                maxlength: String(fmt.digits),
                autocomplete: "off",
            });
            const orderHint = h("span", { class: "ps-fHint" });
            const notesInputs = [];
            const notesGridInput = field("notes", `${uid}-notes`, {
                placeholder: "ハート、198×293",
            });
            const notesManualInput = field("notes", `${uid}-notes-manual`, {
                placeholder: "ハート、198×293",
            });
            notesInputs.push(notesGridInput, notesManualInput);

            const manualFields = [
                labelled(
                    `${uid}-person`,
                    "担当者（任意）",
                    field("personInitial", `${uid}-person`, {
                        placeholder: "M",
                    }),
                ),
                labelled(
                    `${uid}-product`,
                    "品名",
                    field("productName", `${uid}-product`, {
                        placeholder: "テスト封筒",
                    }),
                ),
                labelled(
                    `${uid}-qty`,
                    "部数",
                    field("quantity", `${uid}-qty`, { placeholder: "1,000" }),
                ),
            ];
            const notesGridField = labelled(
                `${uid}-notes`,
                "備考（読点区切り）",
                notesGridInput,
                "ps-fSpan3",
            );
            const notesManualField = labelled(
                `${uid}-notes-manual`,
                "備考（読点区切り）",
                notesManualInput,
            );
            const helpText = h("p", { class: "ps-fHint" });
            const errorText = h("p", {
                class: "ps-fError",
                style: { display: "none" },
            });
            const submit = h("button", {
                type: "button",
                class: "ps-fSubmit",
                style: { background: line.color },
                onClick: () => save(),
            });
            const cancel = h(
                "button",
                {
                    type: "button",
                    class: "ps-fCancel",
                    title: "保存せずに入力内容を破棄して閉じる",
                    onClick: () => onCancel(),
                },
                "キャンセル",
            );

            function sync() {
                const isManual = draft.orderNumberInput.trim().length === 0;
                const formatted = fmt.format(draft.orderNumberInput);
                const canSubmit = isManual
                    ? draft.productName.trim().length > 0 &&
                      draft.quantity.trim().length > 0
                    : Boolean(formatted);
                orderHint.textContent = isManual
                    ? "未入力のため、担当・品名・部数を直接入力してください"
                    : formatted
                      ? `→ ${formatted}`
                      : `${fmt.digits}桁以内の数字を入力してください`;
                for (const el of manualFields)
                    el.style.display = isManual ? "" : "none";
                notesGridField.style.display = isManual ? "none" : "";
                notesManualField.style.display = isManual ? "" : "none";
                helpText.textContent = isManual
                    ? text.manualHelp
                    : text.lookupHelp;
                submit.disabled = !canSubmit || pending;
                cancel.disabled = pending;
                submit.title = isManual
                    ? text.manualButtonTitle
                    : text.lookupButtonTitle;
                submit.textContent = pending
                    ? isManual
                        ? "保存中…"
                        : text.lookupPending
                    : item
                      ? "変更を保存"
                      : "予定を追加";
            }

            async function save() {
                if (pending) return;
                pending = true;
                errorText.style.display = "none";
                sync();
                const result = await service.saveItem({
                    id: item ? item.id : undefined,
                    date: dateKey,
                    line: line.key,
                    orderNumberInput: draft.orderNumberInput,
                    personInitial: draft.personInitial,
                    productName: draft.productName,
                    quantity: draft.quantity,
                    notes: draft.notes
                        .split(/[、,]/)
                        .map((note) => note.trim())
                        .filter((note) => note.length > 0),
                });
                pending = false;
                if (!result.ok) {
                    errorText.textContent = result.error;
                    errorText.style.display = "";
                    sync();
                    return;
                }
                state.drafts.delete(draftKey);
                onSaved(result.item);
            }

            orderInput.addEventListener("keydown", (event) => {
                if (event.key === "Enter" && !submit.disabled) save();
            });

            sync();
            const form = h(
                "div",
                { class: "ps-fCol ps-gap3" },
                h(
                    "div",
                    { class: "ps-fGrid" },
                    h(
                        "div",
                        { class: "ps-fCol ps-gap1" },
                        h(
                            "label",
                            { class: "ps-fLabel", for: `${uid}-order` },
                            "注番（数字のみ・任意）",
                        ),
                        orderInput,
                        orderHint,
                    ),
                    manualFields,
                    notesGridField,
                ),
                notesManualField,
                helpText,
                errorText,
                h("div", { class: "ps-fRow ps-gap3" }, submit, cancel),
            );
            if (!isRestored)
                requestAnimationFrame(
                    () => orderInput.isConnected && orderInput.focus(),
                );
            return form;
        }

        /** Two-step 削除 → 削除 / 取消 confirm (day table row). */
        function deleteControls(item, { container, onError }) {
            const paint = (confirming, busy) => {
                container.replaceChildren();
                if (!opts.permissions.canDelete) return;
                if (confirming) {
                    container.append(
                        h(
                            "button",
                            {
                                type: "button",
                                class: "ps-opBtn ps-opBtnDanger",
                                disabled: busy,
                                title: "この予定を完全に削除する（元に戻せません）",
                                onClick: async () => {
                                    paint(false, true);
                                    onError(null);
                                    const result = await service.deleteItem(
                                        item.id,
                                    );
                                    if (!result.ok) {
                                        paint(false, false);
                                        onError(result.error);
                                        return;
                                    }
                                    refresh();
                                },
                            },
                            "削除",
                        ),
                        h(
                            "button",
                            {
                                type: "button",
                                class: "ps-opBtn",
                                title: "削除をやめる",
                                onClick: () => paint(false, false),
                            },
                            "取消",
                        ),
                    );
                } else {
                    container.append(
                        h(
                            "button",
                            {
                                type: "button",
                                class: "ps-opBtn ps-opBtnDanger",
                                disabled: busy,
                                title: "この予定を削除（もう一度確認が表示されます）",
                                onClick: () => paint(true, false),
                            },
                            "削除",
                        ),
                    );
                }
            };
            paint(false, false);
            return container;
        }

        /* ---------------- 1日 view (page.tsx day branch + schedule-board.tsx) ---------------- */

        function dayPanel(anchor, dateKey, todayKey, monthDayTypes) {
            const dayType = monthDayTypes.get(dateKey) || null;
            const isHolidayToday = isFullyClosed(dayType);
            const isHalfDayToday = isHalfDay(dayType);
            const dayItems = state.items.filter(
                (item) => item.date === dateKey,
            );

            const head = h(
                "div",
                { class: "ps-panelHead" },
                h(
                    "span",
                    { class: "ps-bigDate" },
                    `${anchor.getMonth() + 1}月${anchor.getDate()}日`,
                ),
                h(
                    "span",
                    { class: "ps-dowTag" },
                    `${DOW_JP[anchor.getDay()]}曜日`,
                ),
                dateKey === todayKey
                    ? h("span", { class: "ps-todayBadge" }, "本日")
                    : null,
                isHalfDayToday
                    ? h("span", { class: "ps-halfDayBadge" }, "半日出勤")
                    : null,
                h("span", { class: "ps-divider" }),
                !isHolidayToday
                    ? h(
                          "span",
                          { class: "ps-total" },
                          `受注 ${dayItems.length} 件`,
                      )
                    : null,
            );

            if (isHolidayToday) {
                return h(
                    "div",
                    { class: "ps-weekBlock" },
                    head,
                    h(
                        "div",
                        { class: "ps-holidayShell" },
                        h("div", { class: "ps-holidayStamp" }, "休日"),
                        h("p", { class: "ps-holidayNote" }, text.holidayNote),
                    ),
                );
            }

            return h(
                "div",
                { class: "ps-weekBlock" },
                head,
                h(
                    "div",
                    { class: "ps-tableShell" },
                    h(
                        "table",
                        { class: "ps-table" },
                        h(
                            "thead",
                            null,
                            h(
                                "tr",
                                null,
                                h(
                                    "th",
                                    { style: { width: "210px" } },
                                    "注文情報",
                                ),
                                h("th", null, "品名"),
                                h(
                                    "th",
                                    {
                                        class: "ps-colQty",
                                        style: { width: "140px" },
                                    },
                                    "部数",
                                ),
                                h("th", { class: "ps-colNote" }, "備考"),
                                h("th", { class: "ps-colOp" }),
                            ),
                        ),
                        dayTableBody(dayItems, dateKey),
                    ),
                ),
            );
        }

        const COLUMN_COUNT = 5;

        function dayTableBody(items, dateKey) {
            const byLine = new Map();
            for (const item of items)
                byLine.set(
                    item.line,
                    (byLine.get(item.line) || []).concat(item),
                );
            const tbody = h("tbody");
            const { canCreate } = opts.permissions;

            for (const line of lines) {
                const lineItems = byLine.get(line.key) || [];
                const isAdding = state.addingLine === line.key;
                const draftKey = `day-add|${line.key}|${dateKey}`;

                tbody.append(
                    h(
                        "tr",
                        {
                            class: "ps-sectionRow",
                            style: { background: line.soft },
                        },
                        h(
                            "td",
                            { colspan: COLUMN_COUNT },
                            h(
                                "div",
                                { class: "ps-sectionRowInner" },
                                h(
                                    "span",
                                    { class: "ps-sectionLeft" },
                                    linePill(line, "section"),
                                    line.subtitle
                                        ? h(
                                              "span",
                                              { class: "ps-sectionSubtitle" },
                                              line.subtitle,
                                          )
                                        : null,
                                    h(
                                        "span",
                                        { class: "ps-sectionCount" },
                                        `${lineItems.length} 件`,
                                    ),
                                ),
                                canCreate
                                    ? h(
                                          "button",
                                          {
                                              type: "button",
                                              class: "ps-sectionAdd",
                                              title: isAdding
                                                  ? "入力フォームを閉じる"
                                                  : `${line.label}にこの日の予定を新規追加`,
                                              onClick: () => {
                                                  if (isAdding)
                                                      state.drafts.delete(
                                                          draftKey,
                                                      );
                                                  state.addingLine = isAdding
                                                      ? null
                                                      : line.key;
                                                  render();
                                              },
                                          },
                                          isAdding ? "閉じる" : "＋ 追加",
                                      )
                                    : null,
                            ),
                        ),
                    ),
                );

                if (canCreate && isAdding) {
                    tbody.append(
                        h(
                            "tr",
                            { class: "ps-formRow" },
                            h(
                                "td",
                                { colspan: COLUMN_COUNT },
                                itemForm({
                                    line,
                                    dateKey,
                                    draftKey,
                                    onCancel: () => {
                                        state.drafts.delete(draftKey);
                                        state.addingLine = null;
                                        render();
                                    },
                                    onSaved: () => {
                                        state.addingLine = null;
                                        refresh();
                                    },
                                }),
                            ),
                        ),
                    );
                }

                if (lineItems.length === 0 && line.requiresManagerCheck) {
                    tbody.append(
                        h(
                            "tr",
                            { class: "ps-alertRow" },
                            h(
                                "td",
                                { colspan: COLUMN_COUNT },
                                h(
                                    "div",
                                    { class: "ps-alertNote" },
                                    line.managerCheckMessage ||
                                        text.managerCheckDefault,
                                ),
                            ),
                        ),
                    );
                }
                if (lineItems.length === 0 && !line.requiresManagerCheck) {
                    tbody.append(
                        h(
                            "tr",
                            { class: "ps-emptyRow" },
                            h(
                                "td",
                                { colspan: COLUMN_COUNT },
                                h("span", { class: "ps-stub" }),
                                "本日の予定なし",
                            ),
                        ),
                    );
                }

                for (const item of lineItems)
                    tbody.append(itemRow(item, line, dateKey));
            }
            return tbody;
        }

        function itemRow(item, line, dateKey) {
            const { canEdit } = opts.permissions;
            const draftKey = `day-edit|${item.id}`;

            if (state.editingIds.has(item.id)) {
                return h(
                    "tr",
                    { class: "ps-formRow" },
                    h(
                        "td",
                        { colspan: COLUMN_COUNT },
                        itemForm({
                            item,
                            line,
                            dateKey,
                            draftKey,
                            onCancel: () => {
                                state.drafts.delete(draftKey);
                                state.editingIds.delete(item.id);
                                render();
                            },
                            onSaved: () => {
                                state.editingIds.delete(item.id);
                                refresh();
                            },
                        }),
                    ),
                );
            }

            const highlight = getHighlight(item.highlightColor);
            const errorEl = h("div", {
                class: "ps-opError",
                style: { display: "none" },
            });
            const showError = (message) => {
                errorEl.textContent = message || "";
                errorEl.style.display = message ? "" : "none";
            };

            return h(
                "tr",
                {
                    class: "ps-itemRow",
                    style: {
                        background: highlight ? highlight.soft : line.soft,
                    },
                },
                h(
                    "td",
                    null,
                    h(
                        "div",
                        { class: "ps-orderText" },
                        h(
                            "span",
                            {
                                class: "ps-orderChip",
                                style: { color: line.color },
                            },
                            item.orderNumber || text.manualOrderLabel,
                        ),
                        item.personInitial
                            ? h(
                                  "span",
                                  {
                                      class: "ps-handle",
                                      style: { color: line.color },
                                  },
                                  `@${item.personInitial}`,
                              )
                            : null,
                    ),
                ),
                h(
                    "td",
                    null,
                    h(
                        "div",
                        { class: "ps-productCell" },
                        h("span", {
                            class: "ps-picon",
                            style: {
                                background: `${line.color}1c`,
                                color: line.color,
                            },
                            html: PRODUCT_ICON,
                        }),
                        h(
                            "span",
                            {
                                class: "ps-productNameTxt",
                                title: item.productName,
                            },
                            item.productName,
                        ),
                    ),
                ),
                h(
                    "td",
                    { class: "ps-colQty" },
                    h(
                        "span",
                        { class: "ps-qtyCell", style: { color: line.color } },
                        h("span", {
                            html: QTY_ICON,
                            style: { display: "inline-flex" },
                        }),
                        h("span", { class: "ps-qtyVal" }, item.quantity),
                    ),
                ),
                h(
                    "td",
                    { class: "ps-colNote" },
                    item.notes.length > 0
                        ? h(
                              "div",
                              { class: "ps-notePills" },
                              item.notes.map((note) => {
                                  const tone = noteTone(note);
                                  return h(
                                      "span",
                                      {
                                          class: `ps-pill ps-pill${tone[0].toUpperCase()}${tone.slice(1)}`,
                                      },
                                      note,
                                  );
                              }),
                          )
                        : h("span", { class: "ps-pillNone" }, "—"),
                ),
                h(
                    "td",
                    { class: "ps-opCell" },
                    canEdit ? highlightPicker(item) : null,
                    canEdit
                        ? h(
                              "button",
                              {
                                  type: "button",
                                  class: "ps-opBtn",
                                  title: "この予定の内容を編集",
                                  onClick: () => {
                                      state.editingIds.add(item.id);
                                      render();
                                  },
                              },
                              "編集",
                          )
                        : null,
                    deleteControls(item, {
                        container: h("span"),
                        onError: showError,
                    }),
                    errorEl,
                ),
            );
        }

        /* ---------------- 7日 / 30日 grid (schedule-grid-board.tsx) ---------------- */

        function gridBoard(days, todayKey) {
            const dayKeys = new Set(days.map(dateParam));
            const items = state.items.filter((item) => dayKeys.has(item.date));
            const effectiveDayTypes = buildEffectiveDayTypeMap(
                days,
                state.explicitTypes,
            );

            const byCell = new Map();
            const countByDay = new Map();
            for (const item of items) {
                const cellKey = `${item.line}|${item.date}`;
                byCell.set(cellKey, (byCell.get(cellKey) || []).concat(item));
                countByDay.set(item.date, (countByDay.get(item.date) || 0) + 1);
            }

            const headerRow = h(
                "tr",
                null,
                h("th", { class: "ps-gridCorner" }, "ライン"),
                days.map((day) => {
                    const dayKey = dateParam(day);
                    const explicitType =
                        state.explicitTypes.get(dayKey) || null;
                    const isToday = dayKey === todayKey;
                    return h(
                        "th",
                        {
                            class: cx(
                                "ps-gridDayHeader",
                                isFullyClosed(explicitType) &&
                                    "ps-gridDayHeaderHoliday",
                                isToday && "ps-gridDayHeaderToday",
                            ),
                        },
                        h(
                            "div",
                            { class: "ps-gridDayHeaderInner" },
                            h(
                                "div",
                                { class: "ps-gridDayHeaderTop" },
                                h(
                                    "span",
                                    { class: "ps-gridDayDate" },
                                    `${day.getMonth() + 1}月${day.getDate()}日`,
                                ),
                                h(
                                    "span",
                                    { class: "ps-gridDayDow" },
                                    `${DOW_JP[day.getDay()]}曜`,
                                ),
                                isToday
                                    ? h(
                                          "span",
                                          { class: "ps-todayBadge" },
                                          "本日",
                                      )
                                    : null,
                            ),
                            h(
                                "span",
                                { class: "ps-gridDayCount" },
                                `受注 ${countByDay.get(dayKey) || 0} 件`,
                            ),
                            explicitType
                                ? h(
                                      "span",
                                      {
                                          class: cx(
                                              "ps-gridDayTypeBadge",
                                              isHalfDay(explicitType) &&
                                                  "ps-gridDayTypeBadgeHalf",
                                          ),
                                      },
                                      `${CALENDAR_DAY_PRESENTATION[explicitType].symbol} ${CALENDAR_DAY_PRESENTATION[explicitType].shortLabel}`,
                                  )
                                : null,
                        ),
                    );
                }),
            );

            const tbody = h(
                "tbody",
                null,
                lines.map((line, lineIndex) =>
                    h(
                        "tr",
                        null,
                        h(
                            "td",
                            {
                                class: "ps-gridLineCell",
                                style: { background: line.soft },
                            },
                            linePill(line, "gridLine"),
                            line.subtitle
                                ? h(
                                      "span",
                                      { class: "ps-gridLineSubtitle" },
                                      line.subtitle,
                                  )
                                : null,
                        ),
                        days.map((day) => {
                            const dayKey = dateParam(day);
                            return gridCell({
                                line,
                                lineIndex,
                                dayKey,
                                items:
                                    byCell.get(`${line.key}|${dayKey}`) || [],
                                dayType: effectiveDayTypes.get(dayKey) || null,
                                moveError:
                                    state.moveError &&
                                    state.moveError.cellKey ===
                                        `${line.key}|${dayKey}`
                                        ? state.moveError.message
                                        : null,
                            });
                        }),
                    ),
                ),
            );

            return h(
                "div",
                { class: "ps-gridScroll" },
                h(
                    "table",
                    { class: "ps-gridTable" },
                    h("thead", null, headerRow),
                    tbody,
                ),
            );
        }

        function gridCell({
            line,
            lineIndex,
            dayKey,
            items,
            dayType,
            moveError,
        }) {
            const { canCreate, canEdit } = opts.permissions;
            const td = h("td", {
                class: "ps-gridCell",
                dataset: {
                    gridCell: "true",
                    lineKey: line.key,
                    dateKey: dayKey,
                },
            });

            td.addEventListener("dragover", (event) => {
                if (!canEdit) return;
                if (!parseDragType(event.dataTransfer.types)) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = "move";
            });
            td.addEventListener("dragenter", (event) => {
                if (!canEdit) return;
                if (parseDragType(event.dataTransfer.types))
                    td.classList.add("ps-gridCellDragOver");
            });
            td.addEventListener("dragleave", () =>
                td.classList.remove("ps-gridCellDragOver"),
            );
            td.addEventListener("drop", (event) => {
                event.preventDefault();
                td.classList.remove("ps-gridCellDragOver");
                if (!canEdit) return;
                const payload = parseDragType(event.dataTransfer.types);
                if (!payload) return;
                const sourceLine =
                    lines[payload.sourceLineIndex] &&
                    lines[payload.sourceLineIndex].key;
                if (!sourceLine) return;
                if (sourceLine === line.key && payload.sourceDate === dayKey)
                    return;
                requestDrop(
                    payload.itemId,
                    sourceLine,
                    payload.sourceDate,
                    line.key,
                    dayKey,
                );
            });

            const inner = h(
                "div",
                { class: "ps-gridCellInner" },
                isFullyClosed(dayType)
                    ? h("span", { class: "ps-gridHoliday" }, "休日")
                    : null,
                items.length === 0 && line.requiresManagerCheck
                    ? h(
                          "span",
                          { class: "ps-gridAlert" },
                          line.managerCheckMessage || text.managerCheckDefault,
                      )
                    : null,
                items.length === 0 && !line.requiresManagerCheck
                    ? h("span", { class: "ps-gridEmpty" }, "－")
                    : null,
                items.map((item) => gridChip(item, line, lineIndex, dayKey)),
                moveError
                    ? h("div", { class: "ps-gridPopoverError" }, moveError)
                    : null,
            );

            if (canCreate) {
                const draftKey = `grid-add|${line.key}|${dayKey}`;
                const addBtn = h(
                    "button",
                    {
                        type: "button",
                        class: "ps-gridAddBtn",
                        title: `${line.label}にこの日の予定を新規追加`,
                    },
                    "＋",
                );
                inner.append(
                    bindPopover(addBtn, {
                        key: draftKey,
                        width: 384,
                        onClose: () => state.drafts.delete(draftKey),
                        build: (api, restored) =>
                            itemForm({
                                line,
                                dateKey: dayKey,
                                draftKey,
                                restored,
                                onCancel: () => api.close(),
                                onSaved: () => {
                                    api.close();
                                    refresh();
                                },
                            }),
                    }),
                );
            }

            td.append(inner);
            return td;
        }

        function gridChip(item, line, lineIndex, dateKey) {
            const { canEdit, canDelete } = opts.permissions;
            const pending = state.pendingCopyIds.has(item.id);
            const interactive = canEdit && !pending;
            const highlight = getHighlight(item.highlightColor);
            const orderLabel = item.orderNumber || text.manualOrderLabel;

            const chip = h("div", {
                class: cx("ps-gridChip", pending && "ps-gridChipPending"),
                style: {
                    borderColor: `${line.color}55`,
                    background: highlight ? highlight.soft : null,
                },
                draggable: interactive && !isTouchCapable ? "true" : "false",
                title: pending
                    ? "コピー中…"
                    : canEdit
                      ? "ドラッグして別の日・別のラインへコピーまたは移動"
                      : null,
            });

            // Mouse: HTML5 drag-and-drop.
            chip.addEventListener("dragstart", (event) => {
                if (!interactive) {
                    event.preventDefault();
                    return;
                }
                event.dataTransfer.effectAllowed = "move";
                event.dataTransfer.setData(
                    buildDragType(item.id, lineIndex, dateKey),
                    "",
                );
                chip.classList.add("ps-gridChipDragging");
            });
            chip.addEventListener("dragend", () =>
                chip.classList.remove("ps-gridChipDragging"),
            );

            // Touch: long-press then drag, with a floating ghost.
            chip.addEventListener("pointerdown", (event) => {
                if (event.pointerType !== "touch" || !interactive) return;
                const pointerId = event.pointerId;
                const startX = event.clientX;
                const startY = event.clientY;
                let activated = false;
                let timer = null;

                const cleanup = () => {
                    document.removeEventListener("pointermove", onMove);
                    document.removeEventListener("pointerup", onUp);
                    document.removeEventListener("pointercancel", onUp);
                    if (timer) clearTimeout(timer);
                };
                const activate = () => {
                    activated = true;
                    chip.classList.add("ps-gridChipDragging");
                    ghost.textContent = `${orderLabel} ・ ${item.productName}`;
                    ghost.style.display = "block";
                    ghost.style.left = `${startX}px`;
                    ghost.style.top = `${startY}px`;
                    if (navigator.vibrate) {
                        try {
                            navigator.vibrate(10);
                        } catch {
                            /* ignore */
                        }
                    }
                };
                const cellAt = (x, y) => {
                    const el = document.elementFromPoint(x, y);
                    return el ? el.closest("[data-grid-cell]") : null;
                };
                const onMove = (moveEvent) => {
                    if (moveEvent.pointerId !== pointerId) return;
                    if (!activated) {
                        if (
                            Math.hypot(
                                moveEvent.clientX - startX,
                                moveEvent.clientY - startY,
                            ) > MOVE_CANCEL_PX
                        ) {
                            cleanup();
                            return;
                        }
                        moveEvent.preventDefault();
                        return;
                    }
                    moveEvent.preventDefault();
                    ghost.style.left = `${moveEvent.clientX}px`;
                    ghost.style.top = `${moveEvent.clientY}px`;
                    const cell = cellAt(moveEvent.clientX, moveEvent.clientY);
                    if (dragOverCell !== cell) {
                        if (dragOverCell)
                            dragOverCell.classList.remove(
                                "ps-gridCellDragOver",
                            );
                        if (cell) cell.classList.add("ps-gridCellDragOver");
                        dragOverCell = cell;
                    }
                };
                const onUp = (upEvent) => {
                    if (upEvent.pointerId !== pointerId) return;
                    cleanup();
                    chip.classList.remove("ps-gridChipDragging");
                    ghost.style.display = "none";
                    const targetCell = dragOverCell;
                    if (targetCell)
                        targetCell.classList.remove("ps-gridCellDragOver");
                    dragOverCell = null;
                    if (!activated || !targetCell) return;
                    const targetLine = targetCell.dataset.lineKey;
                    const targetDate = targetCell.dataset.dateKey;
                    if (!targetLine || !targetDate) return;
                    if (targetLine === line.key && targetDate === dateKey)
                        return;
                    requestDrop(
                        item.id,
                        line.key,
                        dateKey,
                        targetLine,
                        targetDate,
                    );
                };
                document.addEventListener("pointermove", onMove, {
                    passive: false,
                });
                document.addEventListener("pointerup", onUp);
                document.addEventListener("pointercancel", onUp);
                timer = setTimeout(activate, LONG_PRESS_MS);
            });

            // Main body = edit popover trigger.
            const main = h(
                "button",
                {
                    type: "button",
                    class: "ps-gridChipMain",
                    disabled: !interactive,
                    title: pending
                        ? "コピー中…"
                        : canEdit
                          ? "クリックしてこの予定を編集"
                          : "編集する権限がありません",
                },
                h(
                    "span",
                    { class: "ps-gridChipOrderRow" },
                    h(
                        "span",
                        {
                            class: "ps-gridChipOrder",
                            style: { color: line.color },
                        },
                        orderLabel,
                    ),
                    item.personInitial
                        ? h(
                              "span",
                              {
                                  class: "ps-gridChipHandle",
                                  style: { color: line.color },
                              },
                              `@${item.personInitial}`,
                          )
                        : null,
                    pending
                        ? h(
                              "span",
                              { class: "ps-gridChipPendingLabel" },
                              "コピー中…",
                          )
                        : null,
                ),
                h(
                    "span",
                    { class: "ps-gridChipProduct", title: item.productName },
                    item.productName,
                ),
                h("span", { class: "ps-gridChipQty" }, item.quantity),
            );
            if (interactive) {
                const draftKey = `grid-edit|${item.id}`;
                bindPopover(main, {
                    key: draftKey,
                    width: 384,
                    onClose: () => state.drafts.delete(draftKey),
                    build: (api, restored) =>
                        itemForm({
                            item,
                            line,
                            dateKey,
                            draftKey,
                            restored,
                            onCancel: () => api.close(),
                            onSaved: () => {
                                api.close();
                                refresh();
                            },
                        }),
                });
            }
            chip.append(main);

            if (interactive) chip.append(highlightPicker(item));

            const errorEl = h("div", {
                class: "ps-gridPopoverError",
                style: { display: "none" },
            });
            if (canDelete && !pending) {
                const slot = h("span", { style: { display: "contents" } });
                const paint = (confirming, busy) => {
                    slot.replaceChildren(
                        confirming
                            ? h(
                                  "span",
                                  { class: "ps-gridChipConfirm" },
                                  h(
                                      "button",
                                      {
                                          type: "button",
                                          disabled: busy,
                                          title: "この予定を完全に削除する（元に戻せません）",
                                          onClick: async () => {
                                              paint(false, true);
                                              errorEl.style.display = "none";
                                              const result =
                                                  await service.deleteItem(
                                                      item.id,
                                                  );
                                              if (!result.ok) {
                                                  paint(false, false);
                                                  errorEl.textContent =
                                                      result.error;
                                                  errorEl.style.display = "";
                                                  return;
                                              }
                                              refresh();
                                          },
                                      },
                                      "削除",
                                  ),
                                  h(
                                      "button",
                                      {
                                          type: "button",
                                          title: "削除をやめる",
                                          onClick: () => paint(false, false),
                                      },
                                      "取消",
                                  ),
                              )
                            : h(
                                  "button",
                                  {
                                      type: "button",
                                      class: "ps-gridChipDelete",
                                      disabled: busy,
                                      title: "この予定を削除（もう一度確認が表示されます）",
                                      onClick: () => paint(true, false),
                                  },
                                  "×",
                              ),
                    );
                };
                paint(false, false);
                chip.append(slot);
            }
            chip.append(errorEl);
            return chip;
        }

        /* ---------------- copy-or-move ---------------- */

        function requestDrop(
            itemId,
            sourceLine,
            sourceDate,
            targetLine,
            targetDate,
        ) {
            if (state.moveError) {
                state.moveError = null;
                render();
            }
            if (activeDialog) activeDialog.close();
            const drop = {
                itemId,
                sourceLine,
                sourceDate,
                targetLine,
                targetDate,
            };
            const target = getLine(targetLine);
            const cancel = () => {
                if (activeDialog) activeDialog.close();
                activeDialog = null;
            };
            const actions = [];
            if (opts.permissions.canCreate) {
                actions.push({
                    label: "コピー",
                    variant: "outline",
                    disabled: state.inflight > 0,
                    onClick: () => {
                        cancel();
                        confirmCopy(drop);
                    },
                });
            }
            if (opts.permissions.canEdit) {
                actions.push({
                    label: "移動",
                    variant: "primary",
                    disabled: state.inflight > 0,
                    onClick: () => {
                        cancel();
                        confirmMove(drop);
                    },
                });
            }
            activeDialog = openDialog({
                title: "コピーしますか、移動しますか？",
                description: `${target ? `${target.label}・${formatDateLabel(targetDate)}` : ""}へドロップしました。コピーすると元の予定は残したまま同じ内容をここに追加し、移動すると元の予定をここへ移します。`,
                actions,
                onCancel: cancel,
            });
        }

        function setInflight(delta) {
            state.inflight += delta;
            if (activeDialog) activeDialog.setDisabled(state.inflight > 0);
        }

        async function confirmMove({ itemId, targetLine, targetDate }) {
            const item = findItem(itemId);
            if (!item) return;
            const previousItems = state.items;
            state.items = state.items.map((candidate) =>
                candidate.id === itemId
                    ? Object.assign({}, candidate, {
                          date: targetDate,
                          line: targetLine,
                      })
                    : candidate,
            );
            render();
            setInflight(1);
            const result = await service.moveItem(
                itemId,
                targetDate,
                targetLine,
            );
            setInflight(-1);
            if (!result.ok) {
                state.items = previousItems;
                state.moveError = {
                    cellKey: `${targetLine}|${targetDate}`,
                    message: result.error,
                };
                render();
                return;
            }
            refresh();
        }

        async function confirmCopy({ itemId, targetLine, targetDate }) {
            const source = findItem(itemId);
            if (!source) return;
            const tempId = `optimistic-${generateId()}`;
            state.moveError = null;
            state.items = state.items.concat(
                Object.assign({}, source, {
                    id: tempId,
                    date: targetDate,
                    line: targetLine,
                    notes: source.notes.slice(),
                }),
            );
            state.pendingCopyIds = new Set(state.pendingCopyIds).add(tempId);
            render();
            setInflight(1);
            const result = await service.copyItem(
                source,
                targetDate,
                targetLine,
            );
            setInflight(-1);
            const clearPending = () => {
                if (!state.pendingCopyIds.has(tempId)) return;
                const next = new Set(state.pendingCopyIds);
                next.delete(tempId);
                state.pendingCopyIds = next;
            };
            if (!result.ok) {
                state.items = state.items.filter(
                    (candidate) => candidate.id !== tempId,
                );
                clearPending();
                state.moveError = {
                    cellKey: `${targetLine}|${targetDate}`,
                    message: result.error,
                };
                render();
                return;
            }
            state.items = state.items.map((candidate) =>
                candidate.id === tempId ? result.item : candidate,
            );
            clearPending();
            render();
            refresh();
        }

        /* ---------------- start ---------------- */

        load();

        return {
            refresh,
            navigate(date, view) {
                navigate(date || dateParam(state.anchor), view || state.view);
            },
            setPermissions(permissions) {
                Object.assign(opts.permissions, permissions || {});
                render();
            },
            getState() {
                return {
                    date: dateParam(state.anchor),
                    view: state.view,
                    items: state.items.map((item) =>
                        Object.assign({}, item, { notes: item.notes.slice() }),
                    ),
                };
            },
            destroy() {
                state.destroyed = true;
                pop.destroy();
                if (activeDialog) activeDialog.close();
                ghost.remove();
                root.remove();
                if (opts.syncUrl)
                    window.removeEventListener("popstate", onPopState);
            },
        };
    }

    /* =========================================================================
     * 13. PUBLIC API
     * ========================================================================= */

    return {
        VERSION,
        mount,
        UserFacingError,
        adapters: {
            livewire: livewireAdapter,
            staticCalendar,
        },
        PRODUCTION_LINES,
        HIGHLIGHT_COLORS,
        DOW_JP,
        CALENDAR_DAY_TYPES,
        CALENDAR_DAY_PRESENTATION,
        DEFAULT_TEXT,
        utils: {
            parseAnchor,
            dateParam,
            startOfWeek,
            buildWeekGrid,
            buildMonthGrid,
            buildMonthDays,
            buildMonthWeekRows,
            isFullyClosed,
            isHalfDay,
            effectiveCalendarDayType,
            buildEffectiveDayTypeMap,
            createOrderNumberFormatter,
            validateItemInput,
            noteTone,
        },
    };
});
