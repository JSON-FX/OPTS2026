import { Button } from '@/Components/ui/button';
import { ButtonHTMLAttributes } from 'react';

export default function SecondaryButton({ type = 'button', ...props }: ButtonHTMLAttributes<HTMLButtonElement>) {
    return <Button type={type} variant="outline" {...props} />;
}
