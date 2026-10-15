import DocumentEditor from '@/Components/Documents/DocumentEditor';
import type { EditorProps, DocumentRecord } from '@/Components/Documents/types';
export default function Edit({ purchaseOrder, ...props }: Omit<EditorProps, 'kind' | 'record'> & { purchaseOrder: DocumentRecord }) { return <DocumentEditor {...props} kind="PO" record={purchaseOrder}/>; }
