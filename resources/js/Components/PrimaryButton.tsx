import { Button } from '@/Components/ui/button';
import { ButtonHTMLAttributes } from 'react';

export default function PrimaryButton(props: ButtonHTMLAttributes<HTMLButtonElement>) {
    return <Button {...props} />;
}
