import { useEffect } from 'react';

type WidgetHandle = { destroy: () => void };
type WidgetWindow = Window & {
    LGUConnectWidget?: {
        mount: (options: {
            connectOrigin: string;
            launch: (context: { signal: AbortSignal }) => Promise<unknown>;
        }) => WidgetHandle;
    };
};

export function ConnectPilotWidget({ origin, launchUrl }: { origin: string; launchUrl: string }) {
    useEffect(() => {
        const browser = window as WidgetWindow;
        let handle: WidgetHandle | undefined;
        let active = true;
        const mount = () => {
            if (!active || !browser.LGUConnectWidget || handle) return;
            try {
                handle = browser.LGUConnectWidget.mount({
                    connectOrigin: origin,
                    launch: async ({ signal }) => {
                        const response = await fetch(launchUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            cache: 'no-store',
                            redirect: 'error',
                            signal,
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                            },
                        });
                        if (!response.ok) throw new Error('Connect launch failed');
                        return response.json();
                    },
                });
            } catch {
                // Procurement navigation remains usable if the widget cannot mount.
            }
        };

        if (browser.LGUConnectWidget) mount();
        else {
            const script = document.createElement('script');
            script.src = '/js/lgu-connect-widget.js';
            script.async = true;
            script.addEventListener('load', mount);
            document.head.appendChild(script);
            return () => {
                active = false;
                script.removeEventListener('load', mount);
                script.remove();
                handle?.destroy();
            };
        }

        return () => {
            active = false;
            handle?.destroy();
        };
    }, [origin, launchUrl]);

    return null;
}
