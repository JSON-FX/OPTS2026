import ProcurementWorkspace from "@/Components/Procurements/ProcurementWorkspace";
import type { ProcurementPageProps } from "@/types/procurements";
export default function Edit(props: ProcurementPageProps) {
    return <ProcurementWorkspace {...props} view="detail" />;
}
