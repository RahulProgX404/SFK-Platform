{{--
    ユーザー情報 popup of every panel (Modules\Core\Filament\Support\UserPopup), in the cached panel script (ThemeAssets):
    one card for the whole page, drawn when a name (`[data-sfk-user]`: name, card lines, profile link) is clicked — under
    the name, above it when there is no room below — and closed by another click, Esc, scrolling or leaving the page. A
    list of hundreds of names thus carries no dropdown component per name. Texts are set as text, never as HTML.
--}}
<script>
    (() => {
        let card = null
        let trigger = null

        const close = () => {
            if (card === null) {
                return
            }
            card.remove()
            card = null
            trigger?.setAttribute('aria-expanded', 'false')
            trigger = null
        }

        const element = (tag, className, text) => {
            const node = document.createElement(tag)
            node.className = className
            if (text !== undefined) {
                node.textContent = text
            }
            return node
        }

        const open = (button) => {
            let data
            try {
                data = JSON.parse(button.dataset.sfkUser)
            } catch (error) {
                return
            }

            close()
            trigger = button
            card = element('div', 'sfk-user-pop')
            card.setAttribute('role', 'dialog')
            card.setAttribute('aria-label', 'ユーザー情報（' + data.n + '）')

            const inner = element('div', 'sfk-user-card')
            const avatar = element('span', 'sfk-user-avatar sfk-user-avatar-lg', Array.from(data.n || '?')[0])
            avatar.setAttribute('aria-hidden', 'true')
            const body = element('div', 'sfk-user-card-body')
            body.append(element('p', 'sfk-user-card-name', data.n))
            for (const line of data.m || []) {
                body.append(element('p', 'sfk-user-card-meta', line))
            }
            if (data.u) {
                const link = element('a', 'sfk-user-card-link', 'プロフィールを表示')
                link.href = data.u
                body.append(link)
            }
            inner.append(avatar, body)
            card.append(inner)
            document.body.append(card)

            const place = button.getBoundingClientRect()
            const size = card.getBoundingClientRect()
            const left = Math.max(8, Math.min(place.left, window.innerWidth - size.width - 8))
            const below = place.bottom + 6
            const top = below + size.height > window.innerHeight - 8 && place.top - size.height - 6 > 8 ? place.top - size.height - 6 : below
            card.style.left = left + 'px'
            card.style.top = top + 'px'
            button.setAttribute('aria-expanded', 'true')
        }

        // capture: a click on a name opens its card and nothing else (the row behind it, a link around it)
        document.addEventListener('click', (event) => {
            const button = event.target instanceof Element ? event.target.closest('[data-sfk-user]') : null
            if (button !== null) {
                event.preventDefault()
                event.stopPropagation()
                if (trigger === button) {
                    close()
                } else {
                    open(button)
                }

                return
            }
            if (card !== null && ! card.contains(event.target)) {
                close()
            }
        }, true)

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && card !== null) {
                const button = trigger
                close()
                button?.focus()
            }
        })
        window.addEventListener('scroll', close, true)
        window.addEventListener('resize', close)
        document.addEventListener('livewire:navigating', close)
    })()
</script>
