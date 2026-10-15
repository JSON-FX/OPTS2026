import DocumentDetail from '@/Components/Documents/DocumentDetail';
import type { DetailProps, DocumentRecord } from '@/Components/Documents/types';
export default function Show({ purchaseRequest, ...props }: Omit<DetailProps, 'kind' | 'record'> & { purchaseRequest: DocumentRecord }) { return <DocumentDetail {...props} kind="PR" record={purchaseRequest}/>; }
