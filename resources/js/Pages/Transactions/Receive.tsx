import HandoffWorkspace from '@/Components/Documents/HandoffWorkspace';
import type { HandoffProps } from '@/Components/Documents/handoff-types';

export default function Receive(props: HandoffProps) {
    return <HandoffWorkspace {...props} mode="receive"/>;
}
