import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import { User, Role, Office } from '@/types/models';
import { FormEventHandler } from 'react';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';

interface Props extends PageProps {
    user: User;
    roles: Role[];
    offices: Office[];
}

export default function Edit({ user, roles, offices }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        role: user.roles?.[0]?.name || '',
        office_id: user.office_id as number | undefined,
        is_active: user.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.users.update', user.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit User
                </h2>
            }
        >
            <Head title="Edit User" />

            <div className="py-12">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* SSO Information (read-only) */}
                            <div className="mb-6 rounded-md bg-muted/50 p-4 space-y-3">
                                <h3 className="text-sm font-medium text-muted-foreground">
                                    SSO Information
                                </h3>
                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <span className="text-muted-foreground">Name:</span>{' '}
                                        <span className="font-medium">{user.name}</span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Email:</span>{' '}
                                        <span className="font-medium">{user.email}</span>
                                    </div>
                                    {user.sso_uuid && (
                                        <div>
                                            <span className="text-muted-foreground">SSO UUID:</span>{' '}
                                            <span className="font-mono text-xs">{user.sso_uuid}</span>
                                        </div>
                                    )}
                                    {user.sso_position && (
                                        <div>
                                            <span className="text-muted-foreground">Position:</span>{' '}
                                            <span className="font-medium">{user.sso_position}</span>
                                        </div>
                                    )}
                                    {user.last_sso_login_at && (
                                        <div className="col-span-2">
                                            <span className="text-muted-foreground">Last SSO Login:</span>{' '}
                                            <span className="font-medium">
                                                {new Date(user.last_sso_login_at).toLocaleString()}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <form onSubmit={submit} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="role">Role</Label>
                                    <Select
                                        value={data.role}
                                        onValueChange={(value) =>
                                            setData('role', value)
                                        }
                                        required
                                    >
                                        <SelectTrigger id="role">
                                            <SelectValue placeholder="Select a role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role.id}
                                                    value={role.name}
                                                >
                                                    {role.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.role && (
                                        <p className="text-sm text-destructive">
                                            {errors.role}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="office_id">
                                        Office (Optional)
                                    </Label>
                                    <Select
                                        value={
                                            data.office_id
                                                ? String(data.office_id)
                                                : 'none'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'office_id',
                                                value === 'none'
                                                    ? undefined
                                                    : parseInt(value)
                                            )
                                        }
                                    >
                                        <SelectTrigger id="office_id">
                                            <SelectValue placeholder="No office" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                No office
                                            </SelectItem>
                                            {offices.map((office) => (
                                                <SelectItem
                                                    key={office.id}
                                                    value={String(office.id)}
                                                >
                                                    {office.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.office_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.office_id}
                                        </p>
                                    )}
                                </div>

                                <div className="flex items-center space-x-3">
                                    <Switch
                                        id="is_active"
                                        checked={data.is_active}
                                        onCheckedChange={(checked) =>
                                            setData('is_active', checked)
                                        }
                                    />
                                    <Label htmlFor="is_active">
                                        Active
                                        {!data.is_active && (
                                            <Badge variant="destructive" className="ml-2">
                                                Deactivated
                                            </Badge>
                                        )}
                                    </Label>
                                </div>

                                <div className="flex items-center justify-end gap-4">
                                    <Link href={route('admin.users.index')}>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </Link>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Update User
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
