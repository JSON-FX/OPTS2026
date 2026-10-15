import DocumentEditor from '@/Components/Documents/DocumentEditor';
import type { EditorProps } from '@/Components/Documents/types';
export default function Create(props: Omit<EditorProps, 'kind'>) { return <DocumentEditor {...props} kind="PO"/>; }
