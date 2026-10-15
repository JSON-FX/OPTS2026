import DocumentEditor from '@/Components/Documents/DocumentEditor';
import type { EditorProps, DocumentRecord } from '@/Components/Documents/types';
export default function Edit({ purchaseRequest, ...props }: Omit<EditorProps, 'kind' | 'record'> & { purchaseRequest: DocumentRecord }) { return <DocumentEditor {...props} kind="PR" record={purchaseRequest}/>; }
