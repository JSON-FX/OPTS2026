import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import type { Voucher } from '@/types/models';

interface Props {
    voucher: Voucher;
}

export default function Edit({ voucher }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        reference_number: voucher.transaction?.reference_number ?? '',
        payee: voucher.payee,
        workflow_id: voucher.transaction?.workflow_id ?? null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('vouchers.update', voucher.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Voucher - {voucher.transaction?.reference_number}
                </h2>
            }
        >
            <Head title={`Edit Voucher ${voucher.transaction?.reference_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Edit Voucher Details</h3>
                        </div>
                        <div className="p-6">
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <InputLabel htmlFor="reference_number" value="Reference Number *" />
                                    <TextInput
                                        id="reference_number"
                                        type="text"
                                        value={data.reference_number}
                                        onChange={(e) => setData('reference_number', e.target.value)}
                                        className="mt-1 block w-full"
                                        required
                                        maxLength={50}
                                    />
                                    <InputError message={errors.reference_number} className="mt-2" />
                                    <p className="mt-1 text-sm text-gray-600">
                                        Free-text format. Validated for uniqueness.
                                    </p>
                                </div>

                                <div>
                                    <InputLabel htmlFor="payee" value="Payee *" />
                                    <TextInput
                                        id="payee"
                                        type="text"
                                        value={data.payee}
                                        onChange={(e) => setData('payee', e.target.value)}
                                        className="mt-1 block w-full"
                                        required
                                        maxLength={255}
                                    />
                                    <InputError message={errors.payee} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="workflow_id" value="Workflow" />
                                    <select
                                        id="workflow_id"
                                        value={data.workflow_id ?? ''}
                                        onChange={(e) =>
                                            setData('workflow_id', e.target.value ? Number(e.target.value) : null)
                                        }
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        disabled
                                    >
                                        <option value="">Workflow routing available in Epic 3</option>
                                    </select>
                                    <p className="mt-1 text-sm text-gray-600">Workflow routing will be enabled in Epic 3</p>
                                </div>

                                <div className="flex items-center gap-4">
                                    <PrimaryButton disabled={processing}>Save Changes</PrimaryButton>
                                    <Link
                                        href={route('vouchers.show', voucher.id)}
                                        className="text-sm text-gray-600 hover:text-gray-900"
                                    >
                                        Cancel
                                    </Link>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
