import DocumentDetail from '@/Components/Documents/DocumentDetail';
import type { DetailProps, DocumentRecord } from '@/Components/Documents/types';
export default function Show({ purchaseOrder, ...props }: Omit<DetailProps, 'kind' | 'record'> & { purchaseOrder: DocumentRecord }) { return <DocumentDetail {...props} kind="PO" record={purchaseOrder}/>; }
