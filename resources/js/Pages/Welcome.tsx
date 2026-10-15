import SSOEntry from "@/Components/Auth/SSOEntry";
import { PageProps } from "@/types";
import { Head } from "@inertiajs/react";

export default function Welcome({ auth }: PageProps) {
    return (
        <>
            <Head title="OPTS — Online Procurement Tracking System" />
            <SSOEntry signedIn={Boolean(auth.user)} />
        </>
    );
}
