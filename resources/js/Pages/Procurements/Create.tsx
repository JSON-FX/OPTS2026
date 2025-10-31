import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';
import { PageProps } from '@/types';
import { Office, Particular, ProcurementFormData, ProcurementFormFields } from '@/types/models';

interface Props extends PageProps {
    offices: Pick<Office, 'id' | 'name'>[];
    particulars: Pick<Particular, 'id' | 'description'>[];
}

export default function Create({ auth, offices, particulars }: Props) {
    const today = useMemo(() => new Date().toISOString().slice(0, 10), []);

    const { data, setData, post, processing, errors, reset } = useForm<ProcurementFormData>({
        end_user_id: '',
        particular_id: '',
        purpose: '',
        abc_amount: '',
        date_of_entry: today,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('procurements.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    New Procurement
                </h2>
            }
        >
            <Head title="New Procurement" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <label htmlFor="end_user_id" className="block text-sm font-medium text-gray-700">
                                        End User Office
                                    </label>
                                    <select
                                        id="end_user_id"
                                        value={data.end_user_id}
                                        onChange={(event) =>
                                            setData('end_user_id', event.target.value)
                                        }
                                        required
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select an office</option>
                                        {offices.map((office) => (
                                            <option key={office.id} value={office.id}>
                                                {office.name}
                                            </option>
                                        ))}
                                    </select>
                                    {'end_user_id' in errors && errors.end_user_id && (
                                        <p className="mt-1 text-sm text-red-600">{errors.end_user_id}</p>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="particular_id" className="block text-sm font-medium text-gray-700">
                                        Particular
                                    </label>
                                    <select
                                        id="particular_id"
                                        value={data.particular_id}
                                        onChange={(event) =>
                                            setData('particular_id', event.target.value)
                                        }
                                        required
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select a particular</option>
                                        {particulars.map((particular) => (
                                            <option key={particular.id} value={particular.id}>
                                                {particular.description}
                                            </option>
                                        ))}
                                    </select>
                                    {'particular_id' in errors && errors.particular_id && (
                                        <p className="mt-1 text-sm text-red-600">{errors.particular_id}</p>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="purpose" className="block text-sm font-medium text-gray-700">
                                        Purpose
                                    </label>
                                    <textarea
                                        id="purpose"
                                        value={data.purpose}
                                        onChange={(event) => setData('purpose', event.target.value)}
                                        maxLength={1000}
                                        required
                                        rows={4}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    {'purpose' in errors && errors.purpose && (
                                        <p className="mt-1 text-sm text-red-600">{errors.purpose}</p>
                                    )}
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label htmlFor="abc_amount" className="block text-sm font-medium text-gray-700">
                                            Approved Budget for Contract (₱)
                                        </label>
                                        <input
                                            id="abc_amount"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value={data.abc_amount}
                                            onChange={(event) => setData('abc_amount', event.target.value)}
                                            required
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        {'abc_amount' in errors && errors.abc_amount && (
                                            <p className="mt-1 text-sm text-red-600">{errors.abc_amount}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label htmlFor="date_of_entry" className="block text-sm font-medium text-gray-700">
                                            Date of Entry
                                        </label>
                                        <input
                                            id="date_of_entry"
                                            type="date"
                                            value={data.date_of_entry}
                                            max={today}
                                        onChange={(event) => setData('date_of_entry', event.target.value)}
                                            required
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        {'date_of_entry' in errors && errors.date_of_entry && (
                                            <p className="mt-1 text-sm text-red-600">{errors.date_of_entry}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-center justify-end gap-4">
                                    <Link
                                        href={route('procurements.index')}
                                        className="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                    >
                                        Cancel
                                    </Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                                    >
                                        Create Procurement
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
