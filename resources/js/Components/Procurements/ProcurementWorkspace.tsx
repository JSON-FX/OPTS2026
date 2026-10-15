import { PhpAmountInput } from "@/Components/Forms/PhpAmountInput";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Checkbox } from "@/Components/ui/checkbox";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/Components/ui/command";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/Components/ui/popover";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import { Tabs, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { Textarea } from "@/Components/ui/textarea";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/Components/ui/tooltip";
import {
    Archive,
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    Clock3,
    FileText,
    FolderOpen,
    ListFilter,
    MoreHorizontal,
    PauseCircle,
    Pencil,
    Plus,
    Receipt,
    Search,
    Settings2,
    ShieldCheck,
    ShoppingBag,
    X,
    XCircle,
} from "lucide-react";
import {
    useEffect,
    useId,
    useRef,
    useState,
    type FormEvent,
    type ReactNode,
} from "react";

import DashboardLayout from "@/Layouts/DashboardLayout";
import {
    activityDate,
    documentName,
    entryDate,
    money,
    procurementRecord,
    procurementStatuses,
    type DirectoryOptions,
    type ProcurementDocument,
    type ProcurementFilters,
    type ProcurementPageProps,
    type ProcurementRecord,
} from "@/types/procurements";
import { Head, Link, router } from "@inertiajs/react";
import "../../../css/procurements.css";
type Modal = "quick" | "create" | "edit" | "document" | "about" | null;
type Filters = {
    office: string;
    particular: string;
    from: string;
    to: string;
    mine: boolean;
};
const emptyFilters: Filters = {
    office: "all",
    particular: "all",
    from: "",
    to: "",
    mine: false,
};
const kinds = ["PR", "PO", "VCH"] as const;
const iconFor = (status: string) =>
    status === "Completed"
        ? CheckCircle2
        : status === "On Hold"
          ? PauseCircle
          : status === "Cancelled"
            ? XCircle
            : Clock3;
function Choice({
    label,
    value,
    onChange,
    children,
    disabled = false,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    children: ReactNode;
    disabled?: boolean;
}) {
    return (
        <Select value={value} onValueChange={onChange} disabled={disabled}>
            <SelectTrigger data-slot="select-trigger" aria-label={label}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent className="op-live op-live-overlay pp-floating">
                {children}
            </SelectContent>
        </Select>
    );
}
function StateBadge({ status }: { status: string }) {
    const Icon = iconFor(status);
    return (
        <Badge
            data-slot="badge"
            variant="outline"
            className={
                "pp-status pp-status-" +
                status.toLowerCase().replaceAll(" ", "-")
            }
        >
            <Icon aria-hidden="true" />
            {status}
        </Badge>
    );
}
function Office({ code, name }: { code: string; name?: string }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="pp-office" tabIndex={0}>
                    {code}
                </span>
            </TooltipTrigger>
            <TooltipContent>{name ?? code}</TooltipContent>
        </Tooltip>
    );
}
function Stages({ record }: { record: ProcurementRecord }) {
    return (
        <div className="pp-stages" aria-label="Linked document progress">
            {kinds.map((kind) => {
                const document = record.documents.find(
                    (item) => item.kind === kind,
                );
                return (
                    <Tooltip key={kind}>
                        <TooltipTrigger asChild>
                            <span
                                tabIndex={0}
                                className={
                                    "pp-stage " +
                                    (document
                                        ? document.status === "Completed"
                                            ? "is-done"
                                            : "is-current"
                                        : "is-missing")
                                }
                            >
                                <span>
                                    {document?.status === "Completed" ? (
                                        <Check />
                                    ) : document ? (
                                        <Clock3 />
                                    ) : (
                                        <span className="pp-stage-dot" />
                                    )}
                                </span>
                                {kind === "VCH" ? "Voucher" : kind}
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {document
                                ? document.reference + " · " + document.status
                                : documentName(kind) + " not created"}
                        </TooltipContent>
                    </Tooltip>
                );
            })}
        </div>
    );
}
function ProcurementFacts({ record }: { record: ProcurementRecord }) {
    return (
        <dl className="pp-facts">
            <div>
                <dt>End user office</dt>
                <dd>
                    <Office code={record.office} name={record.officeName} />
                </dd>
            </div>
            <div>
                <dt>Particular</dt>
                <dd>{record.particular}</dd>
            </div>
            <div>
                <dt>Approved budget for the contract</dt>
                <dd className="pp-money">{money(record.amount)}</dd>
            </div>
            <div>
                <dt>Date of entry</dt>
                <dd>{entryDate(record.date)}</dd>
            </div>
            <div>
                <dt>Created by</dt>
                <dd>{record.creator}</dd>
            </div>
        </dl>
    );
}

// Official shadcn Popover + Command composition; displayed office labels stay abbreviated.
function SearchChoice({
    id,
    label,
    value,
    onChange,
    options,
    placeholder,
    disabled,
    invalid,
    describedBy,
}: {
    id?: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string; keywords?: string[] }[];
    placeholder: string;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
}) {
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => option.value === value);
    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    data-slot="button"
                    id={id}
                    variant="outline"
                    role="combobox"
                    aria-label={label}
                    aria-expanded={open}
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                    disabled={disabled}
                    className="pp-combobox-trigger"
                >
                    <span>{selected?.label ?? (value || placeholder)}</span>
                    <ChevronsUpDown />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="op-live op-live-overlay pp-floating pp-combobox-popup"
                align="start"
            >
                <Command>
                    <CommandInput
                        placeholder={"Search " + label.toLowerCase() + "…"}
                        aria-label={"Search " + label.toLowerCase()}
                    />
                    <CommandList>
                        <CommandEmpty>No matches found.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={option.value}
                                    keywords={[
                                        option.label,
                                        ...(option.keywords ?? []),
                                    ]}
                                    onSelect={() => {
                                        onChange(option.value);
                                        setOpen(false);
                                    }}
                                >
                                    {option.label}
                                    <Check
                                        className={
                                            value === option.value
                                                ? "pp-choice-check"
                                                : "pp-choice-check pp-choice-hidden"
                                        }
                                    />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
function ProcurementForm({
    record,
    unavailable,
    formOptions,
    onSaved,
    onCancel,
}: {
    record?: ProcurementRecord;
    unavailable: boolean;
    formOptions: DirectoryOptions & { today: string };
    onSaved: () => void;
    onCancel: () => void;
}) {
    const prefix = useId();
    const today = formOptions.today;
    const [office, setOffice] = useState(record?.officeId ?? "");
    const [particular, setParticular] = useState(record?.particularId ?? "");
    const [purpose, setPurpose] = useState(record?.purpose ?? "");
    const [amount, setAmount] = useState(record ? String(record.amount) : "");
    const [date, setDate] = useState(record?.date ?? today);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const locked = !!record?.raw.transactions_count;
    const [processing, setProcessing] = useState(false);
    const officeChoices = formOptions.offices.map((item) => ({
        value: String(item.id),
        label: item.abbreviation,
        keywords: [item.name],
    }));
    if (record && !officeChoices.some((item) => item.value === record.officeId))
        officeChoices.push({
            value: record.officeId,
            label: record.office,
            keywords: [record.officeName],
        });
    const particularChoices = formOptions.particulars.map((item) => ({
        value: String(item.id),
        label: item.description,
    }));
    if (
        record &&
        !particularChoices.some((item) => item.value === record.particularId)
    )
        particularChoices.push({
            value: record.particularId,
            label: record.particular,
        });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const next: Record<string, string> = {};
        if (!office) next.office = "Choose an end user office.";
        if (!particular) next.particular = "Choose a particular.";
        if (!purpose.trim())
            next.purpose = "Describe what this procurement is for.";
        if (
            !amount ||
            !Number.isFinite(Number(amount)) ||
            Number(amount) <= 0 ||
            !/^\d+(\.\d{1,2})?$/.test(amount)
        )
            next.amount =
                "Enter a positive amount with up to two decimal places.";
        if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date) || date > today)
            next.date = "Choose a valid date on or before today.";
        setErrors(next);
        if (Object.keys(next).length) return;
        setProcessing(true);
        const payload = {
            end_user_id: Number(office),
            particular_id: Number(particular),
            purpose: purpose.trim(),
            abc_amount: amount,
            date_of_entry: date,
        };
        const options = {
            preserveScroll: true,
            onSuccess: onSaved,
            onFinish: () => setProcessing(false),
            onError: (serverErrors: Record<string, string>) => {
                const names: Record<string, string> = {
                    end_user_id: "office",
                    particular_id: "particular",
                    abc_amount: "amount",
                    date_of_entry: "date",
                };
                setErrors(
                    Object.fromEntries(
                        Object.entries(serverErrors).map(([key, value]) => [
                            names[key] ?? key,
                            value,
                        ]),
                    ),
                );
            },
        };
        if (record)
            router.put(
                route("procurements.update", record.id),
                payload,
                options,
            );
        else router.post(route("procurements.store"), payload, options);
    };
    const error = (name: string) =>
        errors[name] ? (
            <p
                id={prefix + name + "-error"}
                className="pp-field-error"
                role="alert"
            >
                {errors[name]}
            </p>
        ) : null;
    return (
        <form
            className="pp-form"
            data-procurement-id={record?.id}
            onSubmit={submit}
            noValidate
        >
            {unavailable && (
                <div className="pp-notice" role="alert">
                    <ShieldCheck />
                    <p>
                        The SSO office directory is unavailable. Try again
                        before creating a procurement.
                    </p>
                </div>
            )}
            {locked && (
                <p className="pp-lock-note">
                    <ShieldCheck />
                    Office and particular are locked because linked documents
                    exist.
                </p>
            )}
            <div className="pp-form-grid">
                <div className="pp-field">
                    <Label htmlFor={prefix + "office"}>End user office</Label>
                    <SearchChoice
                        id={prefix + "office"}
                        label="End user office"
                        value={office}
                        onChange={setOffice}
                        disabled={locked || unavailable}
                        invalid={!!errors.office}
                        describedBy={
                            errors.office ? prefix + "office-error" : undefined
                        }
                        placeholder="Select an office"
                        options={officeChoices}
                    />
                    <small className="pp-directory-note">
                        {formOptions.offices.length} active offices · SSO
                        directory
                    </small>
                    {error("office")}
                </div>
                <div className="pp-field">
                    <Label htmlFor={prefix + "particular"}>Particular</Label>
                    <SearchChoice
                        id={prefix + "particular"}
                        label="Particular"
                        value={particular}
                        onChange={setParticular}
                        disabled={locked}
                        invalid={!!errors.particular}
                        describedBy={
                            errors.particular
                                ? prefix + "particular-error"
                                : undefined
                        }
                        placeholder="Select a particular"
                        options={particularChoices}
                    />
                    {error("particular")}
                </div>
                <div className="pp-field pp-field-wide">
                    <Label htmlFor={prefix + "purpose"}>Purpose</Label>
                    <Textarea
                        id={prefix + "purpose"}
                        value={purpose}
                        maxLength={1000}
                        onChange={(event) => setPurpose(event.target.value)}
                        placeholder="Describe the goods or services and why they are needed."
                        aria-invalid={!!errors.purpose}
                        aria-describedby={
                            errors.purpose
                                ? prefix + "purpose-error"
                                : undefined
                        }
                    />
                    {error("purpose")}
                </div>
                <div className="pp-field">
                    <Label htmlFor={prefix + "amount"}>
                        Approved budget (PHP)
                    </Label>
                    <PhpAmountInput
                        data-slot="input"
                        id={prefix + "amount"}
                        inputMode="decimal"
                        value={amount}
                        onValueChange={setAmount}
                        placeholder="0.00"
                        aria-invalid={!!errors.amount}
                        aria-describedby={
                            errors.amount ? prefix + "amount-error" : undefined
                        }
                    />
                    {error("amount")}
                </div>
                <div className="pp-field">
                    <Label htmlFor={prefix + "date"}>Date of entry</Label>
                    <Input
                        data-slot="input"
                        id={prefix + "date"}
                        type="date"
                        max={today}
                        value={date}
                        onChange={(event) => setDate(event.target.value)}
                        aria-invalid={!!errors.date}
                        aria-describedby={
                            errors.date ? prefix + "date-error" : undefined
                        }
                    />
                    {error("date")}
                </div>
            </div>
            <p className="pp-prototype-note">
                PR, PO, and voucher documents are linked after creating the
                procurement.
            </p>
            <div className="pp-form-actions">
                <Button
                    data-slot="button"
                    type="button"
                    variant="outline"
                    disabled={processing}
                    onClick={onCancel}
                >
                    Cancel
                </Button>
                <Button
                    data-slot="button"
                    type="submit"
                    disabled={unavailable || processing}
                >
                    {processing
                        ? "Saving…"
                        : record
                          ? "Save changes"
                          : "Create procurement"}
                </Button>
            </div>
        </form>
    );
}

export default function ProcurementWorkspace(
    props: ProcurementPageProps & { view: "register" | "detail" },
) {
    const {
        auth,
        can,
        formOptions,
        activityTimeline = [],
        statusCounts = { total: 0 },
        initialModal,
    } = props;
    const view = props.view;
    const viewer = !can.manage;
    const baseId = useId();
    const title = useRef<HTMLHeadingElement>(null);
    const modalTitle = useRef<HTMLHeadingElement>(null);
    const launcher = useRef<HTMLElement | null>(null);
    const [modal, setModal] = useState<Modal>(initialModal ?? null);
    const lastModal = useRef<Modal>(modal);
    if (modal) lastModal.current = modal;
    const renderedModal = modal ?? lastModal.current;
    const records = (
        props.procurements?.data ??
        (props.procurement ? [props.procurement] : [])
    ).map(procurementRecord);
    const [selectedId, setSelectedId] = useState<number | null>(
        props.procurement?.id ?? records[0]?.id ?? null,
    );
    const selected =
        records.find((record) => record.id === selectedId) ?? records[0];
    // Pin the opened record so background register responses cannot retarget mutations.
    const [actionRecord, setActionRecord] = useState<ProcurementRecord | null>(
        initialModal === "edit" && props.procurement
            ? procurementRecord(props.procurement)
            : null,
    );
    const quickRecord = selected;
    const visible = records;
    const rows = records;
    const total = props.procurements?.total ?? records.length;
    const year = String(props.selectedYear);
    const [message, setMessage] = useState(props.flash?.success ?? "");
    useEffect(
        () => setMessage(props.flash?.success ?? ""),
        [props.flash?.success],
    );
    const defaults: ProcurementFilters = {
        search: "",
        status: "",
        end_user_id: null,
        particular_id: null,
        date_from: "",
        date_to: "",
        my_procurements: false,
        sort: "newest",
        per_page: 6,
    };
    const incoming = { ...defaults, ...props.filters };
    const [query, setQuery] = useState(incoming.search);
    const [status, setStatus] = useState(incoming.status || "all");
    const [filters, setFilters] = useState<Filters>({
        office: incoming.end_user_id ? String(incoming.end_user_id) : "all",
        particular: incoming.particular_id
            ? String(incoming.particular_id)
            : "all",
        from: incoming.date_from,
        to: incoming.date_to,
        mine: incoming.my_procurements,
    });
    const [sort, setSort] = useState(incoming.sort);
    const [pageSize, setPageSize] = useState(String(incoming.per_page));
    const [page, setPage] = useState(props.procurements?.current_page ?? 1);
    const currentPage = props.procurements?.current_page ?? 1;
    const pages = props.procurements?.last_page ?? 1;
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [archiveOpen, setArchiveOpen] = useState(false);
    const [archiveProcessing, setArchiveProcessing] = useState(false);
    const [archiveError, setArchiveError] = useState("");
    const [activeDocument, setActiveDocument] =
        useState<ProcurementDocument | null>(null);
    const [editorKey, setEditorKey] = useState(0);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [activityKind, setActivityKind] = useState("all");
    const [scene, setScene] = useState<"ready" | "loading" | "error">("ready");
    const source = { length: statusCounts.total };
    const officeChoices = (props.options?.offices ?? formOptions.offices).map(
        (item) => ({
            value: String(item.id),
            label: item.abbreviation,
            keywords: [item.name],
        }),
    );
    const particularChoices = (
        props.options?.particulars ?? formOptions.particulars
    ).map((item) => ({ value: String(item.id), label: item.description }));
    const [columns, setColumns] = useState(() => {
        const defaults = {
            office: true,
            progress: true,
            amount: true,
            date: true,
            creator: false,
            particular: false,
        };
        try {
            const saved = JSON.parse(
                localStorage.getItem(
                    "opts_procurement_columns_" + auth.user.id,
                ) ?? "{}",
            );
            for (const key of Object.keys(
                defaults,
            ) as (keyof typeof defaults)[])
                if (typeof saved[key] === "boolean") defaults[key] = saved[key];
        } catch {
            /* Optional storage. */
        }
        return defaults;
    });
    useEffect(() => {
        try {
            localStorage.setItem(
                "opts_procurement_columns_" + auth.user.id,
                JSON.stringify(columns),
            );
        } catch {
            /* Optional storage. */
        }
    }, [columns, auth.user.id]);
    const parameterKey = JSON.stringify({
        query,
        status,
        filters,
        sort,
        pageSize,
        page,
    });
    const expectedKey = JSON.stringify({
        query: incoming.search,
        status: incoming.status || "all",
        filters: {
            office: incoming.end_user_id ? String(incoming.end_user_id) : "all",
            particular: incoming.particular_id
                ? String(incoming.particular_id)
                : "all",
            from: incoming.date_from,
            to: incoming.date_to,
            mine: incoming.my_procurements,
        },
        sort: incoming.sort,
        pageSize: String(incoming.per_page),
        page: currentPage,
    });
    const pendingKey = useRef(expectedKey);
    const lastIncomingKey = useRef(expectedKey);
    const historyNavigation = useRef(false);
    useEffect(() => {
        const markHistory = () => {
            historyNavigation.current = true;
        };
        window.addEventListener("popstate", markHistory);
        return () => window.removeEventListener("popstate", markHistory);
    }, []);
    useEffect(() => {
        if (lastIncomingKey.current === expectedKey) return;
        lastIncomingKey.current = expectedKey;
        // Preserve edits made after this response's request was submitted.
        if (!historyNavigation.current && parameterKey !== pendingKey.current)
            return;
        historyNavigation.current = false;
        pendingKey.current = expectedKey;
        setQuery(incoming.search);
        setStatus(incoming.status || "all");
        setFilters({
            office: incoming.end_user_id ? String(incoming.end_user_id) : "all",
            particular: incoming.particular_id
                ? String(incoming.particular_id)
                : "all",
            from: incoming.date_from,
            to: incoming.date_to,
            mine: incoming.my_procurements,
        });
        setSort(incoming.sort);
        setPageSize(String(incoming.per_page));
        setPage(currentPage);
    }, [expectedKey]);
    const queryParameters = () => ({
        search: query,
        status: status === "all" ? "" : status,
        end_user_id: filters.office === "all" ? "" : filters.office,
        particular_id: filters.particular === "all" ? "" : filters.particular,
        date_from: filters.from,
        date_to: filters.to,
        my_procurements: filters.mine ? 1 : 0,
        sort,
        per_page: pageSize,
        page,
    });
    const reloadRegister = () =>
        router.get(route("procurements.index"), queryParameters(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setScene("loading"),
            onSuccess: () => setScene("ready"),
            onError: () => setScene("error"),
            onFinish: () => setScene((s) => (s === "loading" ? "ready" : s)),
        });
    useEffect(() => {
        if (view !== "register" || parameterKey === pendingKey.current) return;
        const timer = setTimeout(() => {
            pendingKey.current = parameterKey;
            reloadRegister();
        }, 250);
        return () => clearTimeout(timer);
    }, [parameterKey, view]);
    useEffect(() => {
        title.current?.focus({ preventScroll: true });
    }, [view]);
    const filterCount =
        Number(filters.office !== "all") +
        Number(filters.particular !== "all") +
        Number(!!filters.from) +
        Number(!!filters.to) +
        Number(filters.mine);
    const updateFilters = (next: Partial<Filters>) => {
        setFilters((value) => ({ ...value, ...next }));
        setPage(1);
    };
    const resetFilters = () => {
        setFilters(emptyFilters);
        setQuery("");
        setStatus("all");
        setPage(1);
    };
    const registerUrl = () => {
        try {
            return (
                sessionStorage.getItem(
                    "opts_procurement_register_" + auth.user.id,
                ) || route("procurements.index")
            );
        } catch {
            return route("procurements.index");
        }
    };
    useEffect(() => {
        if (view !== "register") return;
        try {
            sessionStorage.setItem(
                "opts_procurement_register_" + auth.user.id,
                window.location.pathname + window.location.search,
            );
        } catch {
            /* Optional storage. */
        }
    }, [view, expectedKey, auth.user.id]);
    const navigate = (next: "register" | "detail", id?: number) => {
        if (next === "register") router.get(registerUrl());
        else if (id) {
            try {
                if (view === "register")
                    sessionStorage.setItem(
                        "opts_procurement_register_" + auth.user.id,
                        window.location.pathname + window.location.search,
                    );
            } catch {
                /* Optional storage. */
            }
            router.get(route("procurements.show", id));
        }
    };
    const openModal = (next: Modal, target?: number | ProcurementRecord) => {
        const record =
            typeof target === "object"
                ? target
                : (records.find((item) => item.id === target) ?? selected);
        const id = typeof target === "number" ? target : record?.id;
        if (id) setSelectedId(id);
        setActionRecord(record ?? null);
        launcher.current =
            document.activeElement instanceof HTMLElement &&
            document.activeElement !== document.body &&
            document.activeElement.getAttribute("role") !== "menuitem"
                ? document.activeElement
                : id
                  ? document.querySelector(
                        '[data-procurement-actions="' + id + '"]',
                    )
                  : null;
        if (next === "create" || next === "edit")
            setEditorKey((value) => value + 1);
        setModal(next);
    };
    const selectRow = (id: number) => {
        setSelectedId(id);
        if (window.matchMedia("(max-width:1100px)").matches)
            openModal("quick", id);
    };
    const restoreFocus = (event: Event) => {
        const target = launcher.current?.isConnected
            ? launcher.current
            : window.matchMedia("(max-width:800px)").matches
              ? document.querySelector<HTMLElement>(
                    '[aria-label="Open navigation"]',
                )
              : title.current;
        if (target) {
            event.preventDefault();
            target.focus();
        }
    };
    const archive = (record: ProcurementRecord) => {
        const id = record.id;
        setActionRecord(record);
        launcher.current = document.querySelector(
            '[data-procurement-actions="' + id + '"]',
        );
        setSelectedId(id);
        setArchiveError("");
        setArchiveOpen(true);
    };
    const confirmArchive = () => {
        if (!actionRecord) return;
        setArchiveProcessing(true);
        router.delete(route("procurements.destroy", actionRecord.id), {
            preserveScroll: true,
            onSuccess: () => {
                setArchiveOpen(false);
                setModal(null);
            },
            onError: () =>
                setArchiveError(
                    "The procurement could not be archived. Please try again.",
                ),
            onFinish: () => setArchiveProcessing(false),
        });
    };
    const recordActions = (record: ProcurementRecord) => (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    data-slot="button"
                    variant="ghost"
                    size="icon"
                    aria-label={"Actions for procurement " + record.id}
                    data-procurement-actions={record.id}
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="op-live op-live-overlay pp-floating"
            >
                <DropdownMenuItem
                    onSelect={() => navigate("detail", record.id)}
                >
                    Open procurement
                </DropdownMenuItem>
                {!viewer && (
                    <>
                        <DropdownMenuItem
                            onSelect={() => openModal("edit", record)}
                        >
                            <Pencil />
                            Edit procurement
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => archive(record)}>
                            <Archive />
                            Archive procurement
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
    const empty = (
        <div className="pp-empty">
            <FolderOpen />
            <h3>
                {source.length
                    ? "No matching procurements"
                    : "No procurements yet"}
            </h3>
            <p>
                {source.length
                    ? "Try another search or clear your filters."
                    : "Procurements for " +
                      year +
                      " will appear here with their linked documents."}
            </p>
            {source.length ? (
                <Button
                    data-slot="button"
                    variant="outline"
                    onClick={resetFilters}
                >
                    Clear filters
                </Button>
            ) : (
                !viewer && (
                    <Button
                        data-slot="button"
                        onClick={() => openModal("create")}
                    >
                        <Plus />
                        New procurement
                    </Button>
                )
            )}
        </div>
    );
    const summary = (record: ProcurementRecord) => (
        <>
            <div className="pp-quick-heading">
                <span>Procurement #{record.id}</span>
                <StateBadge status={record.status} />
            </div>
            <h3>{record.purpose}</h3>
            <ProcurementFacts record={record} />
            <div className="pp-quick-docs">
                <h4>Linked documents</h4>
                {kinds.map((kind) => {
                    const document = record.documents.find(
                        (item) => item.kind === kind,
                    );
                    return (
                        <div key={kind}>
                            <span>{documentName(kind)}</span>
                            <strong>
                                {document ? document.status : "Not created"}
                            </strong>
                        </div>
                    );
                })}
            </div>
            <Button
                data-slot="button"
                className="pp-open-full"
                onClick={() => navigate("detail", record.id)}
            >
                Open procurement
                <ArrowUpRight />
            </Button>
            {viewer && (
                <p className="pp-access-note">
                    <ShieldCheck />
                    You have view-only access.
                </p>
            )}
        </>
    );
    const creationActivity =
        selected?.documents.map((document) => {
            const entity = {
                PR: selected.raw.purchase_request,
                PO: selected.raw.purchase_order,
                VCH: selected.raw.voucher,
            }[document.kind];
            return {
                id: "created-" + document.raw.id,
                action_type: "create",
                transaction_category: document.kind,
                transaction_reference_number: document.reference,
                from_user: entity?.transaction?.created_by ?? null,
                to_user: null,
                from_office: null,
                to_office: null,
                action_taken: "Document created",
                notes: null,
                reason: null,
                is_out_of_workflow: false,
                created_at: document.raw.created_at,
            };
        }) ?? [];
    const activity = [...activityTimeline, ...creationActivity]
        .filter(
            (item) =>
                activityKind === "all" ||
                item.transaction_category === activityKind,
        )
        .sort((a, b) => Date.parse(b.created_at) - Date.parse(a.created_at));
    return (
        <DashboardLayout
            title="Procurements"
            mainId="procurements-main"
            rootClassName="op-procurements"
            toastFlash={false}
            onHelp={() => openModal("about")}
            breadcrumbs={
                view === "detail" && selected
                    ? [
                          {
                              label: "Procurements",
                              href: route("procurements.index"),
                          },
                          { label: "#" + selected.id },
                      ]
                    : [{ label: "Procurements" }]
            }
        >
            <Head
                title={
                    view === "detail" && selected
                        ? "Procurement #" + selected.id
                        : "Procurements"
                }
            />
            <main id="procurements-main" className="op-main pp-main">
                {message && (
                    <div className="pp-success" role="status">
                        <CheckCircle2 />
                        <span>{message}</span>
                        <Button
                            data-slot="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Dismiss confirmation"
                            onClick={() => setMessage("")}
                        >
                            <X />
                        </Button>
                    </div>
                )}
                {props.flash?.error && (
                    <p className="pp-notice" role="alert">
                        {props.flash.error}
                    </p>
                )}
                {view === "register" ? (
                    <>
                        <div className="op-page-head">
                            <div>
                                <h1 ref={title} tabIndex={-1}>
                                    Procurements
                                </h1>
                                <p>
                                    One record for every purchase request,
                                    order, and voucher.
                                </p>
                            </div>
                            <div className="op-page-filters">
                                {!viewer && (
                                    <Button
                                        data-slot="button"
                                        onClick={() => openModal("create")}
                                    >
                                        <Plus />
                                        New procurement
                                    </Button>
                                )}
                            </div>
                        </div>
                        <div className="pp-register-intro">
                            <Tabs
                                value={status}
                                onValueChange={(value) => {
                                    setStatus(value);
                                    setPage(1);
                                }}
                            >
                                <TabsList data-slot="tabs-list">
                                    <TabsTrigger
                                        data-slot="tabs-trigger"
                                        value="all"
                                    >
                                        All
                                        <span className="pp-desktop-label">
                                            {" "}
                                            procurements
                                        </span>
                                        <span>{source.length}</span>
                                    </TabsTrigger>
                                    <TabsTrigger
                                        data-slot="tabs-trigger"
                                        value="In Progress"
                                    >
                                        In progress
                                        <span>
                                            {statusCounts["In Progress"] ?? 0}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger
                                        data-slot="tabs-trigger"
                                        value="Completed"
                                    >
                                        Completed
                                        <span>
                                            {statusCounts["Completed"] ?? 0}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger
                                        data-slot="tabs-trigger"
                                        value="Cancelled"
                                    >
                                        Cancelled
                                        <span>
                                            {statusCounts["Cancelled"] ?? 0}
                                        </span>
                                    </TabsTrigger>
                                    {![
                                        "all",
                                        "In Progress",
                                        "Completed",
                                        "Cancelled",
                                    ].includes(status) && (
                                        <TabsTrigger
                                            data-slot="tabs-trigger"
                                            value={status}
                                        >
                                            {status}
                                        </TabsTrigger>
                                    )}
                                </TabsList>
                            </Tabs>
                            <span className="pp-access-note">
                                <ShieldCheck />
                                {viewer
                                    ? "View-only access"
                                    : "Office permissions apply"}
                            </span>
                        </div>
                        {scene === "error" ? (
                            <section className="pp-empty pp-panel" role="alert">
                                <XCircle />
                                <h2>Procurements could not be loaded</h2>
                                <p>
                                    Try again to reload the register. Your
                                    filters will be kept.
                                </p>
                                <Button
                                    data-slot="button"
                                    onClick={reloadRegister}
                                >
                                    Try again
                                </Button>
                            </section>
                        ) : (
                            <section
                                className="pp-register pp-panel"
                                aria-busy={scene === "loading"}
                            >
                                {scene === "loading" && (
                                    <span role="status" className="sr-only">
                                        Updating procurements…
                                    </span>
                                )}
                                <div className="pp-register-list">
                                    <div className="pp-toolbar">
                                        <div className="op-search">
                                            <Search />
                                            <Input
                                                data-slot="input"
                                                aria-label="Search procurements"
                                                value={query}
                                                onChange={(event) => {
                                                    setQuery(
                                                        event.target.value,
                                                    );
                                                    setPage(1);
                                                }}
                                                placeholder="Search purpose, ID, or document reference…"
                                            />
                                            {query && (
                                                <Button
                                                    data-slot="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Clear search"
                                                    onClick={() => {
                                                        setQuery("");
                                                        setPage(1);
                                                    }}
                                                >
                                                    <X />
                                                </Button>
                                            )}
                                        </div>
                                        <Button
                                            data-slot="button"
                                            variant="outline"
                                            aria-expanded={filtersOpen}
                                            aria-controls={baseId + "-filters"}
                                            onClick={() =>
                                                setFiltersOpen(
                                                    (value) => !value,
                                                )
                                            }
                                        >
                                            <ListFilter />
                                            Filters
                                            {filterCount > 0 && (
                                                <Badge variant="secondary">
                                                    {filterCount}
                                                </Badge>
                                            )}
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    data-slot="button"
                                                    variant="outline"
                                                    aria-label="Customize columns"
                                                >
                                                    <Settings2 />
                                                    <span>Columns</span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent
                                                className="op-live op-live-overlay pp-floating"
                                                align="end"
                                            >
                                                <DropdownMenuLabel>
                                                    Visible columns
                                                </DropdownMenuLabel>
                                                {Object.entries(columns).map(
                                                    ([key, value]) => (
                                                        <DropdownMenuCheckboxItem
                                                            key={key}
                                                            checked={value}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                setColumns(
                                                                    (
                                                                        previous,
                                                                    ) => ({
                                                                        ...previous,
                                                                        [key]:
                                                                            checked ===
                                                                            true,
                                                                    }),
                                                                )
                                                            }
                                                        >
                                                            {
                                                                {
                                                                    office: "End user office",
                                                                    progress:
                                                                        "Document progress",
                                                                    amount: "Approved budget",
                                                                    date: "Date of entry",
                                                                    creator:
                                                                        "Created by",
                                                                    particular:
                                                                        "Particular",
                                                                }[key]
                                                            }
                                                        </DropdownMenuCheckboxItem>
                                                    ),
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                    {filtersOpen && (
                                        <div
                                            id={baseId + "-filters"}
                                            className="pp-filter-panel"
                                        >
                                            <div>
                                                <Label>End user office</Label>
                                                <SearchChoice
                                                    label="Filter end user office"
                                                    value={filters.office}
                                                    onChange={(value) =>
                                                        updateFilters({
                                                            office: value,
                                                        })
                                                    }
                                                    placeholder="All offices"
                                                    options={[
                                                        {
                                                            value: "all",
                                                            label: "All offices",
                                                        },
                                                        ...officeChoices,
                                                    ]}
                                                />
                                            </div>
                                            <div>
                                                <Label>Particular</Label>
                                                <SearchChoice
                                                    label="Filter particular"
                                                    value={filters.particular}
                                                    onChange={(value) =>
                                                        updateFilters({
                                                            particular: value,
                                                        })
                                                    }
                                                    placeholder="All particulars"
                                                    options={[
                                                        {
                                                            value: "all",
                                                            label: "All particulars",
                                                        },
                                                        ...particularChoices,
                                                    ]}
                                                />
                                            </div>
                                            <div>
                                                <Label>Status</Label>
                                                <Choice
                                                    label="Filter status"
                                                    value={status}
                                                    onChange={(value) => {
                                                        setStatus(value);
                                                        setPage(1);
                                                    }}
                                                >
                                                    <SelectItem value="all">
                                                        All statuses
                                                    </SelectItem>
                                                    {procurementStatuses.map(
                                                        (item) => (
                                                            <SelectItem
                                                                key={item}
                                                                value={item}
                                                            >
                                                                {item}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </Choice>
                                            </div>
                                            <div>
                                                <Label
                                                    htmlFor={baseId + "-from"}
                                                >
                                                    Date from
                                                </Label>
                                                <Input
                                                    data-slot="input"
                                                    id={baseId + "-from"}
                                                    type="date"
                                                    value={filters.from}
                                                    max={
                                                        filters.to || undefined
                                                    }
                                                    onChange={(event) =>
                                                        updateFilters({
                                                            from: event.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor={baseId + "-to"}>
                                                    Date to
                                                </Label>
                                                <Input
                                                    data-slot="input"
                                                    id={baseId + "-to"}
                                                    type="date"
                                                    value={filters.to}
                                                    min={
                                                        filters.from ||
                                                        undefined
                                                    }
                                                    onChange={(event) =>
                                                        updateFilters({
                                                            to: event.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <label className="pp-check">
                                                <Checkbox
                                                    checked={filters.mine}
                                                    onCheckedChange={(value) =>
                                                        updateFilters({
                                                            mine:
                                                                value === true,
                                                        })
                                                    }
                                                />
                                                Created by me
                                            </label>
                                            <Button
                                                data-slot="button"
                                                variant="ghost"
                                                onClick={resetFilters}
                                            >
                                                Reset filters
                                            </Button>
                                        </div>
                                    )}
                                    <div className="pp-results-bar">
                                        <span aria-live="polite">
                                            {total}{" "}
                                            {total === 1
                                                ? "procurement"
                                                : "procurements"}
                                            {filterCount ||
                                            query ||
                                            status !== "all"
                                                ? " matching your filters"
                                                : " in " + year}
                                        </span>
                                        <Choice
                                            label="Sort procurements"
                                            value={sort}
                                            onChange={(value) => {
                                                setSort(value);
                                                setPage(1);
                                            }}
                                        >
                                            <SelectItem value="newest">
                                                Newest first
                                            </SelectItem>
                                            <SelectItem value="oldest">
                                                Oldest first
                                            </SelectItem>
                                            <SelectItem value="amount">
                                                Highest budget
                                            </SelectItem>
                                        </Choice>
                                    </div>
                                    {visible.length ? (
                                        <Table className="pp-table">
                                            <TableHeader data-slot="table-header">
                                                <TableRow data-slot="table-row">
                                                    <TableHead data-slot="table-head">
                                                        Procurement
                                                    </TableHead>
                                                    {columns.office && (
                                                        <TableHead data-slot="table-head">
                                                            Office
                                                        </TableHead>
                                                    )}
                                                    {columns.progress && (
                                                        <TableHead data-slot="table-head">
                                                            Documents
                                                        </TableHead>
                                                    )}
                                                    {columns.amount && (
                                                        <TableHead
                                                            data-slot="table-head"
                                                            className="pp-align-right"
                                                        >
                                                            ABC (PHP)
                                                        </TableHead>
                                                    )}
                                                    {columns.date && (
                                                        <TableHead data-slot="table-head">
                                                            Entry date
                                                        </TableHead>
                                                    )}
                                                    {columns.creator && (
                                                        <TableHead data-slot="table-head">
                                                            Created by
                                                        </TableHead>
                                                    )}
                                                    {columns.particular && (
                                                        <TableHead data-slot="table-head">
                                                            Particular
                                                        </TableHead>
                                                    )}
                                                    <TableHead data-slot="table-head">
                                                        Status
                                                    </TableHead>
                                                    <TableHead data-slot="table-head">
                                                        <span className="sr-only">
                                                            Actions
                                                        </span>
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody data-slot="table-body">
                                                {visible.map((record) => (
                                                    <TableRow
                                                        data-slot="table-row"
                                                        key={record.id}
                                                        data-selected={
                                                            quickRecord?.id ===
                                                            record.id
                                                        }
                                                        onClick={(event) => {
                                                            if (
                                                                event.target instanceof
                                                                    Element &&
                                                                event.target.closest(
                                                                    "button,a,input,[role=button],[role=menuitem]",
                                                                )
                                                            )
                                                                return;
                                                            selectRow(
                                                                record.id,
                                                            );
                                                        }}
                                                    >
                                                        <TableCell
                                                            data-slot="table-cell"
                                                            className="pp-purpose-cell"
                                                        >
                                                            <Button
                                                                data-slot="button"
                                                                variant="link"
                                                                className="pp-record-link"
                                                                aria-label={
                                                                    "Preview procurement " +
                                                                    record.id
                                                                }
                                                                onClick={() =>
                                                                    selectRow(
                                                                        record.id,
                                                                    )
                                                                }
                                                            >
                                                                #{record.id}
                                                                <span>
                                                                    {
                                                                        record.purpose
                                                                    }
                                                                </span>
                                                            </Button>
                                                        </TableCell>
                                                        {columns.office && (
                                                            <TableCell
                                                                data-slot="table-cell"
                                                                data-label="Office"
                                                            >
                                                                <Office
                                                                    code={
                                                                        record.office
                                                                    }
                                                                    name={
                                                                        record.officeName
                                                                    }
                                                                />
                                                            </TableCell>
                                                        )}
                                                        {columns.progress && (
                                                            <TableCell
                                                                data-slot="table-cell"
                                                                data-label="Documents"
                                                            >
                                                                <Stages
                                                                    record={
                                                                        record
                                                                    }
                                                                />
                                                            </TableCell>
                                                        )}
                                                        {columns.amount && (
                                                            <TableCell
                                                                data-slot="table-cell"
                                                                data-label="Approved budget"
                                                                className="pp-align-right pp-amount"
                                                            >
                                                                {money(
                                                                    record.amount,
                                                                )}
                                                            </TableCell>
                                                        )}
                                                        {columns.date && (
                                                            <TableCell
                                                                data-slot="table-cell"
                                                                data-label="Date of entry"
                                                            >
                                                                {entryDate(
                                                                    record.date,
                                                                )}
                                                            </TableCell>
                                                        )}
                                                        {columns.creator && (
                                                            <TableCell
                                                                data-slot="table-cell"
                                                                data-label="Created by"
                                                            >
                                                                {record.creator}
                                                            </TableCell>
                                                        )}
                                                        {columns.particular && (
                                                            <TableCell
                                                                data-slot="table-cell"
                                                                data-label="Particular"
                                                            >
                                                                {
                                                                    record.particular
                                                                }
                                                            </TableCell>
                                                        )}
                                                        <TableCell
                                                            data-slot="table-cell"
                                                            data-label="Status"
                                                        >
                                                            <StateBadge
                                                                status={
                                                                    record.status
                                                                }
                                                            />
                                                        </TableCell>
                                                        <TableCell
                                                            data-slot="table-cell"
                                                            className="pp-row-actions"
                                                            onClick={(event) =>
                                                                event.stopPropagation()
                                                            }
                                                        >
                                                            <Button
                                                                data-slot="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={
                                                                    "Quick view procurement " +
                                                                    record.id
                                                                }
                                                                onClick={() =>
                                                                    openModal(
                                                                        "quick",
                                                                        record.id,
                                                                    )
                                                                }
                                                            >
                                                                <ArrowUpRight />
                                                            </Button>
                                                            {recordActions(
                                                                record,
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    ) : (
                                        empty
                                    )}
                                    <div className="pp-table-footer">
                                        <span>
                                            {total
                                                ? (currentPage - 1) *
                                                      Number(pageSize) +
                                                  1
                                                : 0}
                                            –
                                            {Math.min(
                                                currentPage * Number(pageSize),
                                                total,
                                            )}{" "}
                                            of {total}
                                        </span>
                                        <div>
                                            <Choice
                                                label="Rows per page"
                                                value={pageSize}
                                                onChange={(value) => {
                                                    setPageSize(value);
                                                    setPage(1);
                                                }}
                                            >
                                                <SelectItem value="6">
                                                    6 per page
                                                </SelectItem>
                                                <SelectItem value="12">
                                                    12 per page
                                                </SelectItem>
                                                <SelectItem value="24">
                                                    24 per page
                                                </SelectItem>
                                                <SelectItem value="50">
                                                    50 per page
                                                </SelectItem>
                                            </Choice>
                                            <Button
                                                data-slot="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Previous procurement page"
                                                disabled={currentPage === 1}
                                                onClick={() =>
                                                    setPage(currentPage - 1)
                                                }
                                            >
                                                <ChevronLeft />
                                            </Button>
                                            <span className="pp-page-counter" aria-label={`Page ${currentPage} of ${pages}`}>
                                                {currentPage} / {pages}
                                            </span>
                                            <Button
                                                data-slot="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Next procurement page"
                                                disabled={currentPage === pages}
                                                onClick={() =>
                                                    setPage(currentPage + 1)
                                                }
                                            >
                                                <ChevronRight />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <aside
                                    className="pp-quick"
                                    aria-label="Selected procurement preview"
                                >
                                    {quickRecord ? (
                                        summary(quickRecord)
                                    ) : (
                                        <div className="pp-empty">
                                            <FolderOpen />
                                            <h3>Select a procurement</h3>
                                            <p>
                                                Its details and linked documents
                                                appear here.
                                            </p>
                                        </div>
                                    )}
                                </aside>
                            </section>
                        )}
                    </>
                ) : selected ? (
                    <>
                        <Button
                            data-slot="button"
                            variant="ghost"
                            className="pp-back"
                            onClick={() => navigate("register")}
                        >
                            <ArrowLeft />
                            Back to procurements
                        </Button>
                        <div className="op-page-head pp-detail-head">
                            <div>
                                <div className="pp-title-status">
                                    <h1 ref={title} tabIndex={-1}>
                                        Procurement #{selected.id}
                                    </h1>
                                    <StateBadge status={selected.status} />
                                </div>
                                <p>{selected.purpose}</p>
                            </div>
                            {!viewer && (
                                <div className="pp-detail-actions">
                                    <Button
                                        data-slot="button"
                                        variant="outline"
                                        onClick={() =>
                                            openModal("edit", selected.id)
                                        }
                                    >
                                        <Pencil />
                                        Edit procurement
                                    </Button>
                                    {recordActions(selected)}
                                </div>
                            )}
                        </div>
                        <div className="pp-detail-grid">
                            <div className="pp-detail-main">
                                <section className="pp-panel">
                                    <div className="pp-section-heading">
                                        <div>
                                            <h2>Linked documents</h2>
                                            <p>
                                                One purchase request, one
                                                purchase order, and one voucher.
                                            </p>
                                        </div>
                                        <span className="pp-count">
                                            {selected.documents.length} of 3
                                            created
                                        </span>
                                    </div>
                                    <div className="pp-document-list">
                                        {kinds.map((kind, index) => {
                                            const document =
                                                selected.documents.find(
                                                    (item) =>
                                                        item.kind === kind,
                                                );
                                            const preceding =
                                                index === 0 ||
                                                selected.documents.some(
                                                    (item) =>
                                                        item.kind ===
                                                        kinds[index - 1],
                                                );
                                            const Icon =
                                                kind === "PR"
                                                    ? FileText
                                                    : kind === "PO"
                                                      ? ShoppingBag
                                                      : Receipt;
                                            return (
                                                <section
                                                    key={kind}
                                                    className={
                                                        "pp-document " +
                                                        (!document
                                                            ? "pp-document-missing"
                                                            : "")
                                                    }
                                                >
                                                    <div className="pp-document-icon">
                                                        <Icon />
                                                    </div>
                                                    <div className="pp-document-copy">
                                                        <div>
                                                            <h3>
                                                                {documentName(
                                                                    kind,
                                                                )}
                                                            </h3>
                                                            {document && (
                                                                <StateBadge
                                                                    status={
                                                                        document.status
                                                                    }
                                                                />
                                                            )}
                                                        </div>
                                                        {document ? (
                                                            <>
                                                                <Button
                                                                    data-slot="button"
                                                                    variant="link"
                                                                    className="pp-document-reference"
                                                                    onClick={() => {
                                                                        setActiveDocument(
                                                                            document,
                                                                        );
                                                                        openModal(
                                                                            "document",
                                                                        );
                                                                    }}
                                                                >
                                                                    {
                                                                        document.reference
                                                                    }
                                                                    <ArrowUpRight />
                                                                </Button>
                                                                <p>
                                                                    {document.status ===
                                                                    "Completed"
                                                                        ? "Last office: "
                                                                        : "Current office: "}
                                                                    {
                                                                        document.officeName
                                                                    }
                                                                </p>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <strong>
                                                                    Not created
                                                                </strong>
                                                                <p>
                                                                    {preceding
                                                                        ? "Create this document to continue the procurement."
                                                                        : "Create a " +
                                                                          documentName(
                                                                              kinds[
                                                                                  index -
                                                                                      1
                                                                              ],
                                                                          ).toLowerCase() +
                                                                          " first."}
                                                                </p>
                                                            </>
                                                        )}
                                                    </div>
                                                    {document ? (
                                                        <Button
                                                            data-slot="button"
                                                            variant="outline"
                                                            onClick={() => {
                                                                setActiveDocument(
                                                                    document,
                                                                );
                                                                openModal(
                                                                    "document",
                                                                );
                                                            }}
                                                        >
                                                            View document
                                                            <ArrowUpRight />
                                                        </Button>
                                                    ) : (
                                                        !viewer && (
                                                            <Button
                                                                data-slot="button"
                                                                variant="outline"
                                                                disabled={
                                                                    !{
                                                                        PR: props.canCreatePR,
                                                                        PO: props.canCreatePO,
                                                                        VCH: props.canCreateVCH,
                                                                    }[kind]
                                                                }
                                                                onClick={() =>
                                                                    router.get(
                                                                        route(
                                                                            {
                                                                                PR: "procurements.purchase-requests.create",
                                                                                PO: "procurements.purchase-orders.create",
                                                                                VCH: "procurements.vouchers.create",
                                                                            }[
                                                                                kind
                                                                            ],
                                                                            selected.id,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                {"Create " +
                                                                    (kind ===
                                                                    "VCH"
                                                                        ? "voucher"
                                                                        : kind)}
                                                            </Button>
                                                        )
                                                    )}
                                                </section>
                                            );
                                        })}
                                    </div>
                                    <div className="pp-processing-note">
                                        <ShieldCheck />
                                        <p>
                                            {viewer
                                                ? "View-only access. You can inspect documents and their history."
                                                : "Processing follows the assigned office workflow and your SSO role. Open a document to see its available actions."}
                                        </p>
                                    </div>
                                </section>
                                <section className="pp-panel">
                                    <div className="pp-section-heading">
                                        <div>
                                            <h2>Transaction timeline</h2>
                                            <p>
                                                Receipts, endorsements, and
                                                completions across linked
                                                documents.
                                            </p>
                                        </div>
                                        <Choice
                                            label="Timeline document type"
                                            value={activityKind}
                                            onChange={setActivityKind}
                                        >
                                            <SelectItem value="all">
                                                All documents
                                            </SelectItem>
                                            {kinds.map((kind) => (
                                                <SelectItem
                                                    key={kind}
                                                    value={kind}
                                                >
                                                    {documentName(kind)}
                                                </SelectItem>
                                            ))}
                                        </Choice>
                                    </div>
                                    <div className="pp-timeline">
                                        {activity.length ? (
                                            activity.map((item) => (
                                                <div
                                                    className="pp-timeline-item"
                                                    key={item.id}
                                                >
                                                    <span>
                                                        {item.action_type ===
                                                        "complete" ? (
                                                            <CheckCircle2 />
                                                        ) : item.action_type ===
                                                          "endorse" ? (
                                                            <ArrowRight />
                                                        ) : (
                                                            <FileText />
                                                        )}
                                                    </span>
                                                    <div>
                                                        <strong>
                                                            {documentName(
                                                                item.transaction_category,
                                                            )}{" "}
                                                            {{
                                                                create: "created",
                                                                endorse:
                                                                    "endorsed",
                                                                receive:
                                                                    "received",
                                                                complete:
                                                                    "completed",
                                                                hold: "put on hold",
                                                                cancel: "cancelled",
                                                                resume: "resumed",
                                                            }[
                                                                item.action_type
                                                            ] ??
                                                                item.action_type.replaceAll(
                                                                    "_",
                                                                    " ",
                                                                )}
                                                        </strong>
                                                        <p>
                                                            {[
                                                                item.from_office
                                                                    ?.name,
                                                                item.to_office
                                                                    ?.name,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(" to ") ||
                                                                item.action_taken ||
                                                                "Document activity"}
                                                        </p>
                                                        <Button
                                                            data-slot="button"
                                                            variant="link"
                                                            onClick={() => {
                                                                const document =
                                                                    selected.documents.find(
                                                                        (doc) =>
                                                                            doc.reference ===
                                                                            item.transaction_reference_number,
                                                                    );
                                                                if (document) {
                                                                    setActiveDocument(
                                                                        document,
                                                                    );
                                                                    openModal(
                                                                        "document",
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            {
                                                                item.transaction_reference_number
                                                            }
                                                        </Button>
                                                        <small>
                                                            {item.from_user
                                                                ?.name ??
                                                                item.to_user
                                                                    ?.name ??
                                                                "System"}{" "}
                                                            ·{" "}
                                                            {activityDate(
                                                                item.created_at,
                                                            )}
                                                        </small>
                                                        {item.notes && (
                                                            <p>{item.notes}</p>
                                                        )}
                                                        {item.reason && (
                                                            <p>{item.reason}</p>
                                                        )}
                                                        {item.is_out_of_workflow && (
                                                            <span className="pp-lock-note">
                                                                Out-of-workflow
                                                                endorsement
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            ))
                                        ) : (
                                            <p className="pp-inline-empty">
                                                No activity for this document
                                                type yet.
                                            </p>
                                        )}

                                        <div className="pp-timeline-item">
                                            <span>
                                                <FolderOpen />
                                            </span>
                                            <div>
                                                <strong>
                                                    Procurement created
                                                </strong>
                                                <p>
                                                    {selected.creator} ·{" "}
                                                    {selected.officeName}
                                                </p>
                                                <small>
                                                    {activityDate(
                                                        selected.raw.created_at,
                                                    )}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                            <aside className="pp-detail-side">
                                <section className="pp-panel">
                                    <div className="pp-section-heading">
                                        <h2>Procurement information</h2>
                                    </div>
                                    <div className="pp-summary-body">
                                        <ProcurementFacts record={selected} />
                                        <h3>Purpose</h3>
                                        <p>{selected.purpose}</p>
                                    </div>
                                </section>
                                <section className="pp-panel">
                                    <button
                                        className="pp-history-toggle"
                                        aria-expanded={historyOpen}
                                        onClick={() =>
                                            setHistoryOpen((value) => !value)
                                        }
                                    >
                                        <span>Status history</span>
                                        <ChevronDown />
                                    </button>
                                    {historyOpen && (
                                        <div className="pp-history">
                                            {selected.raw.status_history
                                                ?.length ? (
                                                selected.raw.status_history.map(
                                                    (item) => (
                                                        <div key={item.id}>
                                                            <StateBadge
                                                                status={
                                                                    item.new_status
                                                                }
                                                            />
                                                            <p>
                                                                {item.changed_by
                                                                    ?.name ??
                                                                    "System"}
                                                            </p>
                                                            <small>
                                                                {activityDate(
                                                                    item.created_at,
                                                                )}
                                                            </small>
                                                            {item.reason && (
                                                                <p>
                                                                    {
                                                                        item.reason
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                    ),
                                                )
                                            ) : (
                                                <p>
                                                    No status changes recorded.
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </section>
                            </aside>
                        </div>
                    </>
                ) : (
                    empty
                )}
                <footer className="op-page-footer">
                    <span>Local Government of Quezon, Bukidnon</span>
                    <span>Online Procurement Tracking System</span>
                </footer>
            </main>
            <Dialog
                open={modal !== null}
                onOpenChange={(open) => {
                    if (!open) setModal(null);
                }}
            >
                <DialogContent
                    overlayClassName="op-modal-overlay"
                    className={
                        "op-root op-live pp-dialog " +
                        (renderedModal === "create" || renderedModal === "edit"
                            ? "pp-editor-dialog"
                            : "")
                    }
                    onOpenAutoFocus={(event) => {
                        if (renderedModal === "quick") {
                            event.preventDefault();
                            modalTitle.current?.focus();
                        }
                    }}
                    onCloseAutoFocus={restoreFocus}
                >
                    <DialogHeader data-slot="dialog-header">
                        <DialogTitle
                            data-slot="dialog-title"
                            className="pp-dialog-title"
                            ref={modalTitle}
                            tabIndex={-1}
                        >
                            {renderedModal === "create"
                                ? "New procurement"
                                : renderedModal === "edit"
                                  ? "Edit procurement"
                                  : renderedModal === "quick"
                                    ? "Procurement preview"
                                    : renderedModal === "document"
                                      ? "Document overview"
                                      : "Help & reference"}
                        </DialogTitle>
                        <DialogDescription data-slot="dialog-description">
                            {renderedModal === "create" ||
                            renderedModal === "edit"
                                ? "Start with procurement details. PR, PO, and voucher are linked separately."
                                : renderedModal === "document"
                                  ? "Full office names and assigned workflow route."
                                  : renderedModal === "quick"
                                    ? "A quick view of this procurement and its documents."
                                    : "Using the procurements register and document workflows."}
                        </DialogDescription>
                    </DialogHeader>
                    {(renderedModal === "create" || renderedModal === "edit") &&
                        !viewer && (
                            <ProcurementForm
                                key={editorKey}
                                record={
                                    renderedModal === "edit"
                                        ? (actionRecord ?? undefined)
                                        : undefined
                                }
                                unavailable={!formOptions.offices.length}
                                formOptions={formOptions}
                                onSaved={() => setModal(null)}
                                onCancel={() => setModal(null)}
                            />
                        )}{" "}
                    {renderedModal === "quick" && actionRecord && (
                        <div className="pp-quick pp-quick-modal">
                            {summary(actionRecord)}
                        </div>
                    )}
                    {renderedModal === "document" && activeDocument && (
                        <div className="pp-document-overview">
                            <StateBadge status={activeDocument.status} />
                            <h3>{activeDocument.reference}</h3>
                            <p>
                                {activeDocument.status === "Completed"
                                    ? "Last office"
                                    : "Current office"}
                                : <strong>{activeDocument.officeName}</strong>
                            </p>
                            <h4>Document route</h4>
                            <ol>
                                {activeDocument.raw.workflow_steps.map(
                                    (step, index) => {
                                        const current =
                                            activeDocument.raw
                                                .current_step_id === step.id &&
                                            activeDocument.status !==
                                                "Completed";
                                        const currentOrder =
                                            activeDocument.raw.workflow_steps.find(
                                                (item) =>
                                                    item.id ===
                                                    activeDocument.raw
                                                        .current_step_id,
                                            )?.step_order;
                                        const done =
                                            activeDocument.status ===
                                                "Completed" ||
                                            (currentOrder !== undefined &&
                                                step.step_order < currentOrder);
                                        return (
                                            <li
                                                key={step.id}
                                                className={
                                                    done
                                                        ? "is-done"
                                                        : current
                                                          ? "is-current"
                                                          : ""
                                                }
                                            >
                                                <span>
                                                    {done ? (
                                                        <Check />
                                                    ) : (
                                                        index + 1
                                                    )}
                                                </span>
                                                <div>
                                                    <strong>
                                                        {step.office_name}
                                                    </strong>
                                                    <small>
                                                        {done
                                                            ? "Step completed"
                                                            : current
                                                              ? "Current office"
                                                              : "Upcoming office"}
                                                    </small>
                                                </div>
                                            </li>
                                        );
                                    },
                                )}
                            </ol>
                            {!activeDocument.raw.workflow_steps.length && (
                                <p className="pp-inline-empty">
                                    No workflow route assigned.
                                </p>
                            )}
                            {activeDocument.raw.url && (
                                <Button
                                    data-slot="button"
                                    asChild
                                    className="pp-open-full"
                                >
                                    <Link href={activeDocument.raw.url}>
                                        Open document <ArrowUpRight />
                                    </Link>
                                </Button>
                            )}
                            {activeDocument.raw.next_action && (
                                <Button
                                    data-slot="button"
                                    asChild
                                    variant="outline"
                                >
                                    <Link
                                        href={
                                            activeDocument.raw.next_action.url
                                        }
                                    >
                                        {activeDocument.raw.next_action.label}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                    {renderedModal === "about" && (
                        <div className="pp-about">
                            <p>
                                Use the register to search, filter, change
                                columns, preview a record, and open its full
                                detail page. Create, edit, and archive actions
                                follow your SSO role and assigned office.
                            </p>
                            <p>
                                Office selectors use abbreviations. Timelines
                                keep full office names. Guest maps to Viewer,
                                standard to Endorser, and
                                administrator/super_administrator to
                                Administrator.
                            </p>
                            <p>
                                Open a linked document to receive, endorse or
                                complete it when your assigned office and
                                workflow allow the action.
                            </p>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
            <AlertDialog
                open={archiveOpen}
                onOpenChange={(open) => {
                    if (!archiveProcessing) setArchiveOpen(open);
                }}
            >
                <AlertDialogContent
                    data-slot="alert-dialog-content"
                    className="op-root op-live pp-dialog"
                    onCloseAutoFocus={restoreFocus}
                >
                    <AlertDialogHeader data-slot="alert-dialog-header">
                        <AlertDialogTitle
                            data-slot="alert-dialog-title"
                            className="pp-dialog-title"
                        >
                            Archive procurement #{actionRecord?.id}?
                        </AlertDialogTitle>
                        <AlertDialogDescription data-slot="alert-dialog-description">
                            This hides the procurement from active lists. Linked
                            transactions remain available.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {archiveError && (
                        <p role="alert" className="pp-field-error">
                            {archiveError}
                        </p>
                    )}
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={archiveProcessing}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            disabled={archiveProcessing}
                            onClick={(event) => {
                                event.preventDefault();
                                confirmArchive();
                            }}
                        >
                            {archiveProcessing
                                ? "Archiving…"
                                : "Archive procurement"}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>{" "}
        </DashboardLayout>
    );
}
