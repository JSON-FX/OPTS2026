import DocumentDetail from '@/Components/Documents/DocumentDetail';
import type { DetailProps, DocumentRecord } from '@/Components/Documents/types';
export default function Show({ voucher, ...props }: Omit<DetailProps, 'kind' | 'record'> & { voucher: DocumentRecord }) { return <DocumentDetail {...props} kind="VCH" record={voucher}/>; }
