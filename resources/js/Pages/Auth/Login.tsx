import SSOEntry from "@/Components/Auth/SSOEntry";
import { Head } from "@inertiajs/react";

export default function Login({ ssoError }: { ssoError?: string }) {
    return (
        <>
            <Head title="Sign in" />
            <SSOEntry ssoError={ssoError} />
        </>
    );
}
