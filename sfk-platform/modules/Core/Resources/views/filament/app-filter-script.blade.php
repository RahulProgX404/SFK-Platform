{{--
    Alpine components of the app lists, rendered in <head> of every panel by BasePanelProvider:
    - sfkAppFilter: the 絞り込む dialog (core::filament.app-filter). The funnel opens it with the list's current content
      (AppListRecords::appFilterState()); 適用 / 保存 go to applyAppFilter / saveAppFilter.
    - sfkViewEditor: the 一覧 screen of アプリの設定 (core::filament.app-list-views); 保存 goes to saveView.
    Both share the 条件 / ソート editor (filterEditor, markup core::filament.filter-editor).
--}}
<script>
    document.addEventListener('alpine:init', () => {
        let uid = 0
        const key = () => ++uid
        const pad = (number) => String(number).padStart(2, '0')
        const iso = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`

        /** 条件 / ソート rows and the calendar of date conditions. */
        const filterEditor = (config) => ({
            config,
            match: 'and',
            conditions: [],
            sorts: [],
            picker: { key: null, year: 2000, month: 1 },

            load(state) {
                this.match = state.match === 'or' ? 'or' : 'and'
                this.conditions = (state.conditions || []).map((condition) => ({
                    key: key(),
                    field: condition.field,
                    operator: condition.operator,
                    values: [...(condition.values || [])],
                    mode: condition.mode || 'specified',
                    time: condition.time || '',
                    sub: condition.sub || 'all',
                }))
                if (this.conditions.length === 0) {
                    this.conditions.push(this.blankCondition())
                }
                this.sorts = (state.sorts || []).map((sort) => ({ key: key(), field: sort.field, direction: sort.direction }))
                if (this.sorts.length === 0 && this.config.sortable.length > 0) {
                    this.addSort()
                }
            },

            blankCondition() {
                return { key: key(), field: '', operator: '', values: [], mode: 'specified', time: '', sub: 'all' }
            },

            field(row) {
                return this.config.fields.find((field) => field.name === row.field) || null
            },

            operator(row) {
                return this.field(row)?.operators.find((operator) => operator.value === row.operator) || null
            },

            changeField(row) {
                const field = this.field(row)
                row.operator = field ? field.operators[0].value : ''
                row.values = field && ['choice', 'user'].includes(field.type) ? [] : ['']
                row.mode = 'specified'
                row.time = ''
                row.sub = 'all'
            },

            changeMode(row) {
                const subs = this.subOptions(row)
                if (subs.length > 0 && ! subs.some((sub) => sub.value === row.sub)) {
                    row.sub = 'all'
                }
                this.picker.key = null
            },

            subOptions(row) {
                return this.field(row)?.modes.find((mode) => mode.value === row.mode)?.subs || []
            },

            /** none | text | multi | list | date */
            valueKind(row) {
                const field = this.field(row)
                const operator = this.operator(row)
                if (! field || ! operator || ! operator.needsValue) {
                    return 'none'
                }
                if (['choice', 'user'].includes(field.type)) {
                    return 'list'
                }
                if (['date', 'datetime'].includes(field.type)) {
                    return 'date'
                }
                if (row.values.length === 0) {
                    row.values.push('')
                }

                return operator.value === 'one_of' ? 'multi' : 'text'
            },

            hasConditions() {
                return this.conditions.some((row) => row.field !== '')
            },

            addCondition() {
                this.conditions.push(this.blankCondition())
            },

            removeCondition(index) {
                this.conditions.splice(index, 1)
            },

            clearAll() {
                this.conditions = [this.blankCondition()]
                this.match = 'and'
            },

            addSort() {
                const used = this.sorts.map((sort) => sort.field)
                const next = this.config.sortable.find((field) => ! used.includes(field.name)) || this.config.sortable[0]
                this.sorts.push({ key: key(), field: next.name, direction: 'asc' })
            },

            filterPayload() {
                return {
                    match: this.match,
                    conditions: this.conditions
                        .filter((row) => row.field !== '')
                        .map(({ field, operator, values, mode, time, sub }) => ({ field, operator, values, mode, time, sub })),
                    sorts: this.sorts.map(({ field, direction }) => ({ field, direction })),
                }
            },

            todayIso() {
                return iso(new Date())
            },

            openPicker(row) {
                const match = /^(\d{4})-(\d{2})-\d{2}$/.exec(row.values[0] || '')
                const today = new Date()
                this.picker.year = match ? Number(match[1]) : today.getFullYear()
                this.picker.month = match ? Number(match[2]) : today.getMonth() + 1
                this.picker.key = row.key
            },

            pickDate(row, value) {
                row.values = [value]
                this.picker.key = null
            },

            /** Keeps the calendar inside the dialog / screen: moved left when it would stick out on the right. */
            placeCalendar(el) {
                el.style.left = '0px'
                const box = (el.closest('.sfk-filter-dialog, .sfk-view-editor') || document.body).getBoundingClientRect()
                const over = el.getBoundingClientRect().right - (box.right - 12)
                if (over > 0) {
                    el.style.left = `${-over}px`
                }
            },

            shiftMonth(delta) {
                const date = new Date(this.picker.year, this.picker.month - 1 + delta, 1)
                this.picker.year = date.getFullYear()
                this.picker.month = date.getMonth() + 1
            },

            pickerYears() {
                const years = []
                for (let year = this.picker.year - 10; year <= this.picker.year + 10; year++) {
                    years.push(year)
                }

                return years
            },

            /** Six weeks from the Sunday before the 1st of the shown month. */
            calendarWeeks() {
                const first = new Date(this.picker.year, this.picker.month - 1, 1)
                const start = new Date(first)
                start.setDate(1 - first.getDay())
                const weeks = []
                for (let week = 0; week < 6; week++) {
                    const days = []
                    for (let weekday = 0; weekday < 7; weekday++) {
                        const date = new Date(start)
                        date.setDate(start.getDate() + week * 7 + weekday)
                        days.push({ iso: iso(date), day: date.getDate(), inMonth: date.getMonth() === first.getMonth() })
                    }
                    weeks.push(days)
                }

                return weeks
            },
        })

        window.Alpine.data('sfkAppFilter', (config) => ({
            ...filterEditor(config),
            isOpen: false,
            busy: false,
            naming: false,
            viewName: '',
            saveError: null,
            wire: null,

            init() {
                this.wire = this.$wire
            },

            open(state) {
                this.load(state || {})
                this.isOpen = true
                this.$nextTick(() => document.querySelector('.sfk-filter-dialog .sfk-filter-field')?.focus())
            },

            close() {
                this.isOpen = false
                this.naming = false
                this.picker.key = null
            },

            async apply() {
                this.busy = true
                try {
                    await this.wire.applyAppFilter(this.filterPayload())
                    this.close()
                } finally {
                    this.busy = false
                }
            },

            startSave() {
                this.viewName = ''
                this.saveError = null
                this.naming = true
                this.$nextTick(() => this.$refs.viewName?.focus())
            },

            async save() {
                this.busy = true
                try {
                    this.saveError = await this.wire.saveAppFilter(this.filterPayload(), this.viewName)
                    if (! this.saveError) {
                        this.close()
                    }
                } finally {
                    this.busy = false
                }
            },
        }))

        /**
         * 一覧 screen: 一覧名, 表示形式, the columns of 表形式 (dragged from the field list on the left, or added by click;
         * reordered by drag or ⚙ → 左へ移動 / 右へ移動; ⚙ → 削除), 日付 / タイトル of カレンダー形式, 絞り込み and ソート.
         */
        window.Alpine.data('sfkViewEditor', (config) => ({
            ...filterEditor(config),
            name: config.view.name,
            type: config.view.type,
            columns: [...config.view.columns],
            dateField: config.view.dateField || '',
            titleField: config.view.titleField || '',
            search: '',
            columnMenu: null,
            dragging: null,
            dropIndex: null,
            errors: {},
            busy: false,
            wire: null,

            init() {
                this.wire = this.$wire
                this.load(config.view.filter || {})
            },

            /** Drag and drop (HTML5): a field from the list or a placed column; the drop point decides the position. */
            startDrag(event, name, source) {
                this.dragging = { name, source }
                event.dataTransfer.effectAllowed = source === 'palette' ? 'copy' : 'move'
                event.dataTransfer.setData('text/plain', name)
            },

            endDrag() {
                this.dragging = null
                this.dropIndex = null
            },

            dragOver(event) {
                if (! this.dragging) {
                    return
                }
                event.dataTransfer.dropEffect = this.dragging.source === 'palette' ? 'copy' : 'move'
                const items = [...this.$refs.columns.querySelectorAll('[data-column]')]
                this.dropIndex = items.filter((item) => {
                    const box = item.getBoundingClientRect()

                    return box.bottom < event.clientY
                        || (box.top <= event.clientY && event.clientY <= box.bottom && box.left + box.width / 2 < event.clientX)
                }).length
            },

            drop() {
                if (! this.dragging) {
                    return
                }
                const { name, source } = this.dragging
                const index = this.dropIndex ?? this.columns.length
                source === 'palette' ? this.addColumn(name, index) : this.moveColumn(name, index)
                this.endDrag()
            },

            /** Moves a placed column so that it ends up before the column now at `index` (columns.length = last). */
            moveColumn(name, index) {
                const from = this.columns.indexOf(name)
                if (from === -1) {
                    return
                }
                const columns = this.columns.filter((column) => column !== name)
                columns.splice(from < index ? index - 1 : index, 0, name)
                this.columns = columns
                this.columnMenu = null
            },

            columnLabel(name) {
                return this.config.columns.find((column) => column.name === name)?.label || name
            },

            palette() {
                const search = this.search.trim().toLowerCase()

                return this.config.columns.filter((column) => ! this.columns.includes(column.name)
                    && (search === '' || column.label.toLowerCase().includes(search)))
            },

            addColumn(name, index = null) {
                if (this.columns.includes(name)) {
                    return
                }
                index === null ? this.columns.push(name) : this.columns.splice(index, 0, name)
                delete this.errors.columns
            },

            removeColumn(name) {
                this.columns = this.columns.filter((column) => column !== name)
                this.columnMenu = null
            },

            addAllColumns() {
                this.config.columns.forEach((column) => this.addColumn(column.name))
            },

            clearColumns() {
                this.columns = []
            },

            async saveView() {
                this.busy = true
                try {
                    this.errors = (await this.wire.saveView({
                        name: this.name,
                        type: this.type,
                        columns: this.columns,
                        dateField: this.dateField,
                        titleField: this.titleField,
                        filter: this.filterPayload(),
                    })) || {}
                } finally {
                    this.busy = false
                }
            },
        }))
    })
</script>
