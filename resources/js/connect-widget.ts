import { router } from '@inertiajs/react';

type WidgetConfig = { origin: string; launchUrl: string; identity: string };
type WidgetHandle = { destroy: () => void };
type WidgetWindow = Window & {
    LGUConnectWidget?: {
        mount: (options: {
            connectOrigin: string;
            onBadge: (count: number) => void;
            launch: (context: { signal: AbortSignal }) => Promise<unknown>;
        }) => WidgetHandle;
    };
};

/** Keep the iframe alive across Inertia page changes, but never across identities. */
export function initializeConnectWidget(initialProps: Record<string, unknown>) {
    const browser = window as WidgetWindow;
    let current: WidgetConfig | null = null;
    let handle: WidgetHandle | undefined;
    let script: HTMLScriptElement | undefined;
    let badge: HTMLSpanElement | undefined;
    let revision = 0;

    const update = (props: Record<string, unknown>) => {
        const next = props.connectWidget as WidgetConfig | null | undefined;
        if (current?.identity === next?.identity && current?.origin === next?.origin
            && current?.launchUrl === next?.launchUrl) return;
        revision += 1;
        const expected = revision;
        handle?.destroy();
        handle = undefined;
        badge?.remove();
        badge = undefined;
        script?.remove();
        script = undefined;
        current = next ?? null;
        if (!current) return;
        const config = current;
        const mount = () => {
            if (revision !== expected || !browser.LGUConnectWidget) return;
            try {
                const unreadBadge = document.createElement('span');
                badge = unreadBadge;
                unreadBadge.hidden = true;
                unreadBadge.setAttribute('data-connect-unread', '');
                unreadBadge.setAttribute('role', 'status');
                unreadBadge.setAttribute('aria-live', 'polite');
                unreadBadge.setAttribute('aria-atomic', 'true');
                // Match the pinned loader's bottom-right launcher without accessing its private DOM.
                unreadBadge.style.cssText = 'position:fixed;right:14px;bottom:58px;z-index:2147483001;min-width:22px;height:22px;padding:0 5px;box-sizing:border-box;border:2px solid white;border-radius:999px;background:#b91c1c;color:white;font:700 11px/18px system-ui,sans-serif;text-align:center;pointer-events:none';
                handle = browser.LGUConnectWidget.mount({
                    connectOrigin: config.origin,
                    onBadge: (count) => {
                        if (revision !== expected || !Number.isSafeInteger(count) || count < 0) return;
                        unreadBadge.textContent = count === 0 ? '' : count >= 99 ? '99+' : String(count);
                        unreadBadge.setAttribute('aria-label', `${count >= 99 ? '99 or more' : count} unread Connect messages`);
                        unreadBadge.hidden = count === 0;
                    },
                    launch: async ({ signal }) => {
                        const response = await fetch(config.launchUrl, {
                            method: 'POST', credentials: 'same-origin', cache: 'no-store',
                            redirect: 'error', signal,
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                            },
                        });
                        if (!response.ok) throw new Error('Connect launch failed');
                        return response.json();
                    },
                });
                document.body.appendChild(unreadBadge);
            } catch {
                badge?.remove();
                badge = undefined;
                // A chat failure must not prevent EDTS navigation or document work.
            }
        };
        if (browser.LGUConnectWidget) mount();
        else {
            script = document.createElement('script');
            script.src = '/js/lgu-connect-widget.js';
            script.async = true;
            script.addEventListener('load', mount, { once: true });
            document.head.appendChild(script);
        }
    };
    update(initialProps);
    const unsubscribe = router.on('navigate', (event) => update(event.detail.page.props));
    return () => {
        revision += 1;
        unsubscribe();
        badge?.remove();
        handle?.destroy();
        script?.remove();
    };
}
