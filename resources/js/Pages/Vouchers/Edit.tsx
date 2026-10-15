import DocumentEditor from '@/Components/Documents/DocumentEditor';
import type { EditorProps, DocumentRecord } from '@/Components/Documents/types';
export default function Edit({ voucher, ...props }: Omit<EditorProps, 'kind' | 'record'> & { voucher: DocumentRecord }) { return <DocumentEditor {...props} kind="VCH" record={voucher}/>; }
