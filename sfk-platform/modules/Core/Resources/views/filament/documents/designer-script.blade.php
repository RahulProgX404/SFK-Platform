{{--
    sfkDocumentDesigner: the Alpine component of the 帳票テンプレート designer (core::filament.documents.designer).
    Positions and sizes are in mm (as saved, Modules\Core\Domain\Documents\TemplateLayout); the page is drawn at
    96 dpi × 表示倍率. The background is drawn by pdf.js (public/vendor/pdfjs, Apache-2.0), loaded only here.
--}}
<script>
    window.sfkDocumentDesigner = (config) => {
        // pdf.js objects stay out of Alpine's reactive proxies (their private fields break through a proxy)
        let pdf = null
        let renderTask = null

        const PX_PER_MM = 96 / 25.4

        const defaults = {
            font: 'gothic', size: 10.5, bold: false, color: '#000000', border: false,
            align: 'left', valign: 'middle', overflow: 'shrink',
        }

        const newId = () => 'e' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6)

        const round1 = (value) => Math.round(value * 10) / 10

        return {
            config,
            name: config.name,
            pages: config.pages.length ? config.pages : [{ width: 210, height: 297 }],
            elements: config.elements.map((element) => ({ ...defaults, ...JSON.parse(JSON.stringify(element)) })),
            page: 0,
            zoom: 1,
            snap: true,
            search: '',
            selectedId: null,
            previewRecord: '',
            errors: {},
            busy: false,
            dirty: false,
            dragging: null,
            gesture: null,

            get sel() {
                return this.elements.find((element) => element.id === this.selectedId) ?? null
            },

            init() {
                this.onPointerMove = (event) => this.pointerMove(event)
                this.onPointerUp = () => this.pointerUp()
                this.loadBackground()
            },

            destroy() {
                this.pointerUp()
                renderTask?.cancel()
                pdf?.destroy()
            },

            /** px per mm at the chosen 表示倍率 */
            scale() {
                return PX_PER_MM * this.zoom
            },

            pageSize() {
                return this.pages[this.page] ?? this.pages[0]
            },

            sheetStyle() {
                const size = this.pageSize()

                return `width:${size.width * this.scale()}px;height:${size.height * this.scale()}px`
            },

            async loadBackground() {
                if (!this.config.background) {
                    return
                }

                try {
                    const pdfjs = await import(this.config.pdfjs)
                    pdfjs.GlobalWorkerOptions.workerSrc = this.config.pdfjsWorker
                    pdf = await pdfjs.getDocument({
                        url: this.config.background,
                        cMapUrl: this.config.pdfjsCmaps,
                        cMapPacked: true,
                        standardFontDataUrl: this.config.pdfjsFonts,
                        isEvalSupported: false,
                    }).promise
                    await this.renderBackground()
                } catch (error) {
                    console.error('[sfk-designer]', error)
                }
            },

            async renderBackground() {
                const canvas = this.$refs.canvas
                if (!pdf || !canvas) {
                    return
                }

                renderTask?.cancel()
                const pdfPage = await pdf.getPage(Math.min(this.page + 1, pdf.numPages))
                const ratio = window.devicePixelRatio || 1
                // PDF units are pt (1/72 inch)
                const viewport = pdfPage.getViewport({ scale: this.scale() * 25.4 / 72 * ratio })
                canvas.width = Math.floor(viewport.width)
                canvas.height = Math.floor(viewport.height)

                renderTask = pdfPage.render({ canvasContext: canvas.getContext('2d'), viewport })
                try {
                    await renderTask.promise
                } catch (error) {
                    // cancelled by a newer drawing
                }
            },

            showPage(index) {
                this.page = Math.max(0, Math.min(this.pages.length - 1, Number(index)))
                if (this.sel && this.sel.page !== this.page) {
                    this.selectedId = null
                }
                this.$nextTick(() => this.renderBackground())
            },

            pageElements() {
                return this.elements.filter((element) => element.page === this.page)
            },

            // --- labels -------------------------------------------------------------------------------------

            matches(label) {
                return this.search === '' || String(label).toLowerCase().includes(this.search.toLowerCase())
            },

            filtered(list) {
                return list.filter((item) => this.matches(item.label))
            },

            fieldLabel(name) {
                return [...this.config.fields, ...this.config.system].find((field) => field.value === name)?.label ?? name
            },

            tableColumns(table) {
                return this.config.tables.find((item) => item.name === table)?.columns ?? []
            },

            columnLabel(element, field) {
                return this.tableColumns(element.table).find((column) => column.name === field)?.label ?? field
            },

            typeLabel(element) {
                if (element.type === 'table') {
                    return 'テーブル：' + (this.config.tables.find((item) => item.name === element.table)?.label ?? element.table)
                }

                return element.type === 'text' ? '固定テキスト' : 'フィールド'
            },

            displayText(element) {
                return element.type === 'text' ? element.text : (element.prefix || '') + this.fieldLabel(element.field) + (element.suffix || '')
            },

            describe(element) {
                return element.type === 'table' ? this.typeLabel(element) : this.displayText(element)
            },

            // --- drawing ------------------------------------------------------------------------------------

            tableWidth(element) {
                return element.columns.reduce((sum, column) => sum + Number(column.width || 0), 0)
            },

            elementSize(element) {
                return element.type === 'table'
                    ? { w: this.tableWidth(element), h: element.rowHeight * element.rows }
                    : { w: element.w, h: element.h }
            },

            elementStyle(element) {
                const s = this.scale()
                const size = this.elementSize(element)

                return `left:${element.x * s}px;top:${element.y * s}px;width:${size.w * s}px;height:${size.h * s}px`
            },

            fontStyle(element) {
                const family = element.font === 'mincho'
                    ? '"MS Mincho", "Yu Mincho", "Hiragino Mincho ProN", serif'
                    : '"MS Gothic", "Meiryo", "Hiragino Kaku Gothic ProN", sans-serif'

                return `font-family:${family};font-size:${element.size * 96 / 72 * this.zoom}px;font-weight:${element.bold ? 700 : 400};color:${element.color}`
            },

            textStyle(element) {
                const justify = { left: 'flex-start', center: 'center', right: 'flex-end' }[element.align] ?? 'flex-start'
                const items = { top: 'flex-start', middle: 'center', bottom: 'flex-end' }[element.valign] ?? 'center'

                return `${this.fontStyle(element)};justify-content:${justify};align-items:${items};text-align:${element.align};`
                    + `white-space:${element.overflow === 'wrap' ? 'pre-wrap' : 'pre'};padding:0 ${0.5 * this.scale()}px`
            },

            tableStyle(element) {
                const row = element.rowHeight * this.scale()

                return `${this.fontStyle(element)};background-size:100% ${row}px`
            },

            columnStyle(element, column) {
                const justify = { left: 'flex-start', center: 'center', right: 'flex-end' }[column.align] ?? 'flex-start'

                return `width:${column.width * this.scale()}px;height:${element.rowHeight * this.scale()}px;justify-content:${justify};padding:0 ${0.5 * this.scale()}px`
            },

            // --- adding -------------------------------------------------------------------------------------

            make(payload, point) {
                const base = { id: newId(), page: this.page, ...defaults }
                const size = this.pageSize()

                if (payload.type === 'table') {
                    const columns = this.tableColumns(payload.table).slice(0, 6)
                    const x = point ? point.x : 10
                    const width = Math.max(20, Math.min(180, size.width - x - 10))

                    return {
                        ...base, type: 'table', table: payload.table, rowHeight: 7, rows: 10,
                        columns: columns.map((column) => ({ field: column.name, width: round1(width / Math.max(1, columns.length)), align: 'left', format: 'display' })),
                    }
                }

                if (payload.type === 'text') {
                    return { ...base, type: 'text', w: 40, h: 7, text: 'テキスト' }
                }

                return { ...base, type: 'field', w: 50, h: 7, field: payload.field, format: 'display', prefix: '', suffix: '' }
            },

            /** Adds an element at a point of the page (mm), or near the top left corner. */
            addAt(payload, point) {
                const element = this.make(payload, point)
                if (element.type === 'table' && element.columns.length === 0) {
                    return
                }

                const size = this.pageSize()
                const offset = (this.pageElements().length % 10) * 5
                element.x = this.round(point ? point.x : 10 + offset)
                element.y = this.round(point ? point.y : 10 + offset)
                element.x = Math.max(0, Math.min(size.width - 1, element.x))
                element.y = Math.max(0, Math.min(size.height - 1, element.y))

                this.elements.push(element)
                this.selectedId = element.id
                this.dirty = true
            },

            dragStart(event, payload) {
                this.dragging = payload
                event.dataTransfer.effectAllowed = 'copy'
                event.dataTransfer.setData('text/plain', JSON.stringify(payload))
            },

            drop(event) {
                let payload = this.dragging
                try {
                    payload = JSON.parse(event.dataTransfer.getData('text/plain')) ?? payload
                } catch (error) {
                    // dragged from elsewhere: use what dragStart kept
                }
                this.dragging = null
                if (!payload || !payload.type) {
                    return
                }

                const rect = this.$refs.sheet.getBoundingClientRect()
                this.addAt(payload, { x: (event.clientX - rect.left) / this.scale(), y: (event.clientY - rect.top) / this.scale() })
            },

            // --- moving and resizing ------------------------------------------------------------------------

            round(value) {
                return this.snap ? Math.round(value) : round1(value)
            },

            select(id) {
                this.selectedId = id
            },

            startMove(event, element) {
                this.selectedId = element.id
                this.gesture = { kind: 'move', id: element.id, x0: event.clientX, y0: event.clientY, x: element.x, y: element.y }
                this.listen()
            },

            startResize(event, element) {
                const size = this.elementSize(element)
                this.gesture = {
                    kind: 'resize', id: element.id, x0: event.clientX, y0: event.clientY, w: size.w, h: size.h,
                    widths: element.type === 'table' ? element.columns.map((column) => Number(column.width)) : [],
                }
                this.listen()
            },

            listen() {
                window.addEventListener('pointermove', this.onPointerMove)
                window.addEventListener('pointerup', this.onPointerUp)
            },

            pointerMove(event) {
                const gesture = this.gesture
                const element = gesture ? this.elements.find((item) => item.id === gesture.id) : null
                if (!element) {
                    return
                }

                const s = this.scale()
                const dx = (event.clientX - gesture.x0) / s
                const dy = (event.clientY - gesture.y0) / s
                if (Math.abs(dx) + Math.abs(dy) < 0.2) {
                    return
                }

                const size = this.pageSize()
                if (gesture.kind === 'move') {
                    element.x = Math.max(0, Math.min(size.width - 1, this.round(gesture.x + dx)))
                    element.y = Math.max(0, Math.min(size.height - 1, this.round(gesture.y + dy)))
                } else {
                    const width = Math.max(2, this.round(gesture.w + dx))
                    const height = Math.max(2, this.round(gesture.h + dy))
                    if (element.type === 'table') {
                        const factor = width / Math.max(1, gesture.w)
                        element.columns.forEach((column, index) => column.width = Math.max(1, round1(gesture.widths[index] * factor)))
                        element.rows = Math.max(1, Math.min(200, Math.round(height / element.rowHeight)))
                    } else {
                        element.w = width
                        element.h = height
                    }
                }
                this.dirty = true
            },

            pointerUp() {
                this.gesture = null
                window.removeEventListener('pointermove', this.onPointerMove)
                window.removeEventListener('pointerup', this.onPointerUp)
            },

            // --- editing ------------------------------------------------------------------------------------

            remove() {
                this.elements = this.elements.filter((element) => element.id !== this.selectedId)
                this.selectedId = null
                this.dirty = true
            },

            duplicate() {
                if (!this.sel) {
                    return
                }

                const copy = JSON.parse(JSON.stringify(this.sel))
                copy.id = newId()
                copy.x = round1(copy.x + 3)
                copy.y = round1(copy.y + 3)
                this.elements.push(copy)
                this.selectedId = copy.id
                this.dirty = true
            },

            moveColumn(index, direction) {
                const columns = this.sel.columns
                const target = index + direction
                if (target < 0 || target >= columns.length) {
                    return
                }

                columns.splice(target, 0, columns.splice(index, 1)[0])
                this.dirty = true
            },

            removeColumn(index) {
                if (this.sel.columns.length > 1) {
                    this.sel.columns.splice(index, 1)
                    this.dirty = true
                }
            },

            addColumn() {
                const options = this.tableColumns(this.sel.table)
                const used = this.sel.columns.map((column) => column.field)
                const next = options.find((option) => !used.includes(option.name)) ?? options[0]
                if (next) {
                    this.sel.columns.push({ field: next.name, width: 20, align: 'left', format: 'display' })
                    this.dirty = true
                }
            },

            onKey(event) {
                if (event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable], .fi-modal')) {
                    return
                }
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                    event.preventDefault()
                    this.save()

                    return
                }

                const element = this.sel
                if (!element) {
                    return
                }

                const step = event.shiftKey ? 5 : (this.snap ? 1 : 0.5)
                const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }
                if (moves[event.key]) {
                    element.x = Math.max(0, round1(element.x + moves[event.key][0]))
                    element.y = Math.max(0, round1(element.y + moves[event.key][1]))
                } else if (event.key === 'Delete' || event.key === 'Backspace') {
                    this.remove()
                } else if (event.key === 'Escape') {
                    this.selectedId = null

                    return
                } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'd') {
                    this.duplicate()
                } else {
                    return
                }

                event.preventDefault()
                this.dirty = true
            },

            // --- saving -------------------------------------------------------------------------------------

            async save() {
                this.busy = true
                try {
                    const errors = await this.$wire.saveDesign({ name: this.name, elements: this.elements })
                    this.errors = errors ?? {}
                    if (!errors) {
                        this.dirty = false
                    }
                } finally {
                    this.busy = false
                }

                return Object.keys(this.errors).length === 0
            },

            async replaceBackground() {
                if (this.dirty && !(await this.save())) {
                    return
                }

                this.$wire.mountAction('replaceBackground')
            },

            preview() {
                this.$refs.previewLayout.value = JSON.stringify(this.elements)
                this.$refs.previewRecordInput.value = this.previewRecord
                this.$refs.previewForm.submit()
            },
        }
    }
</script>
