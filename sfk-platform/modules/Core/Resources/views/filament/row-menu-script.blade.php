{{--
    The menu of 「…」 of the app list rows (core::filament.tables.row-menu), in the cached panel script (ThemeAssets): one
    menu for the page, under the clicked 「…」 (above it when there is no room below), with Filament's dropdown look. 編集する
    follows its link; 削除する mounts the table's delete action of that row on the list's Livewire component, so its
    confirmations and checks are Filament's. Closed by another click, Esc, scrolling or leaving the page; ↑ / ↓ move
    between the items. Texts are set as text, never as HTML.
--}}
<script>
    (() => {
        let menu = null
        let trigger = null

        const close = () => {
            if (menu === null) {
                return
            }
            menu.remove()
            menu = null
            trigger?.setAttribute('aria-expanded', 'false')
            trigger = null
        }

        const icon = (name) => {
            const box = document.createElement('span')
            box.className = 'fi-icon fi-size-md'
            const glyph = document.createElement('span')
            glyph.className = 'sfk-i sfk-i-' + name
            box.append(glyph)
            return box
        }

        const open = (button, byKeyboard) => {
            let data
            try {
                data = JSON.parse(button.dataset.sfkRowMenu)
            } catch (error) {
                return
            }

            close()
            trigger = button
            menu = document.createElement('div')
            menu.className = 'fi-dropdown-panel sfk-row-menu-panel'
            menu.setAttribute('role', 'menu')
            const list = document.createElement('div')
            list.className = 'fi-dropdown-list'

            for (const item of data.items || []) {
                const entry = document.createElement(item.u ? 'a' : 'button')
                entry.className = 'fi-dropdown-list-item' + (item.d ? ' fi-color fi-color-danger fi-text-color-700' : '')
                entry.setAttribute('role', 'menuitem')
                if (item.u) {
                    entry.href = item.u
                } else {
                    entry.type = 'button'
                    entry.addEventListener('click', () => {
                        const host = button.closest('[wire\\:id]')
                        const component = host && window.Livewire ? window.Livewire.find(host.getAttribute('wire:id')) : null
                        close()
                        component?.mountAction(item.a, {}, { recordKey: data.k, table: true })
                    })
                }
                const label = document.createElement('span')
                label.className = 'fi-dropdown-list-item-label'
                label.textContent = item.l
                entry.append(icon(item.i || 'more'), label)
                list.append(entry)
            }
            menu.append(list)
            document.body.append(menu)

            const place = button.getBoundingClientRect()
            const size = menu.getBoundingClientRect()
            const left = Math.max(8, Math.min(place.right - size.width, window.innerWidth - size.width - 8))
            const below = place.bottom + 4
            const top = below + size.height > window.innerHeight - 8 && place.top - size.height - 4 > 8 ? place.top - size.height - 4 : below
            menu.style.left = left + 'px'
            menu.style.top = top + 'px'
            button.setAttribute('aria-expanded', 'true')
            if (byKeyboard) {
                menu.querySelector('[role="menuitem"]')?.focus()
            }
        }

        // capture: a click on 「…」 opens the menu and nothing else (the row's link behind it)
        document.addEventListener('click', (event) => {
            const button = event.target instanceof Element ? event.target.closest('[data-sfk-row-menu]') : null
            if (button !== null) {
                event.preventDefault()
                event.stopPropagation()
                if (trigger === button) {
                    close()
                } else {
                    open(button, event.detail === 0)
                }

                return
            }
            if (menu !== null && ! menu.contains(event.target)) {
                close()
            }
        }, true)

        document.addEventListener('keydown', (event) => {
            if (menu === null) {
                return
            }
            if (event.key === 'Escape') {
                const button = trigger
                close()
                button?.focus()
            } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                const items = [...menu.querySelectorAll('[role="menuitem"]')]
                const at = items.indexOf(document.activeElement)
                const next = event.key === 'ArrowDown' ? (at + 1) % items.length : (at - 1 + items.length) % items.length
                items[next]?.focus()
                event.preventDefault()
            }
        })
        window.addEventListener('scroll', close, true)
        window.addEventListener('resize', close)
        document.addEventListener('livewire:navigating', close)
    })()
</script>
