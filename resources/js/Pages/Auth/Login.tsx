import { Button } from '@/Components/ui/button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

export default function Login({
    ssoError,
}: {
    ssoError?: string;
}) {
    return (
        <GuestLayout>
            <Head title="Log in" />

            <div className="space-y-6 text-center">
                <div>
                    <h2 className="text-lg font-semibold text-gray-900">
                        Welcome to OPTS
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Online Procurement Tracking System
                    </p>
                </div>

                {ssoError && (
                    <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                        {ssoError}
                    </div>
                )}

                <Button
                    className="w-full"
                    size="lg"
                    onClick={() => {
                        window.location.href = route('sso.redirect');
                    }}
                >
                    Login with LGU-SSO
                </Button>

                <p className="text-xs text-muted-foreground">
                    You will be redirected to the LGU Single Sign-On portal to authenticate.
                </p>
            </div>
        </GuestLayout>
    );
}
