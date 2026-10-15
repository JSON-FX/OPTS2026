import { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import { useDebouncedCallback } from "use-debounce";
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    FolderOpen,
    Search,
    X,
} from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/Components/ui/tooltip";
import { DocumentStatus } from "./DocumentDetail";
import type {
    DashboardDocument,
    DashboardFilters,
    DashboardProps,
} from "@/types/dashboard";

export default function DocumentRegister({
    documents,
    filters,
    selectedYear,
    loading,
    selectedId,
    onFilters: visitFilters,
    onSelect,
    onPage,
}: {
    documents: DashboardProps["documents"];
    filters: DashboardFilters;
    selectedYear: number;
    loading: boolean;
    selectedId: number | null;
    onPage?: (page: number) => void;
    onFilters: (changes: Partial<DashboardFilters>) => void;
    onSelect: (document: DashboardDocument, forceModal: boolean) => void;
}) {
    const [query, setQuery] = useState(filters.search);
    const search = useDebouncedCallback(
        (value: string) => visitFilters({ search: value }),
        400,
    );
    useEffect(() => setQuery(filters.search), [filters.search]);
    useEffect(() => () => search.cancel(), [search]);
    const handleSearch = (value: string) => {
        setQuery(value);
        search(value);
    };
    const rows = documents.data;
    const selected =
        rows.find((document) => document.id === selectedId) ?? rows[0];
    const ascending = filters.sort === "reference_asc";
    return (
        <div className="op-records">
            {" "}
            <form
                className="op-record-toolbar"
                onSubmit={(event) => {
                    event.preventDefault();
                    search.cancel();
                    visitFilters({ search: query });
                }}
            >
                <div className="op-search">
                    <Search />
                    <Input
                        aria-label="Search documents"
                        disabled={loading}
                        maxLength={100}
                        placeholder="Search reference or office…"
                        value={query}
                        onChange={(event) => handleSearch(event.target.value)}
                    />
                    {query && (
                        <Button
                            type="button"
                            disabled={loading}
                            size="icon"
                            variant="ghost"
                            aria-label="Clear search"
                            onClick={() => {
                                search.cancel();
                                setQuery("");
                                visitFilters({ search: "" });
                            }}
                        >
                            <X />
                        </Button>
                    )}
                </div>
                <Select
                    disabled={loading}
                    value={filters.category}
                    onValueChange={(value) =>
                        visitFilters({
                            category: value as DashboardFilters["category"],
                        })
                    }
                >
                    <SelectTrigger aria-label="Document category">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent className="op-live-overlay">
                        <SelectItem value="all">All documents</SelectItem>
                        <SelectItem value="PR">Purchase requests</SelectItem>
                        <SelectItem value="PO">Purchase orders</SelectItem>
                        <SelectItem value="VCH">Vouchers</SelectItem>
                    </SelectContent>
                </Select>
            </form>
            {filters.active_only && (
                <div className="op-active-filter">
                    <span>Showing active workload documents</span>
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => visitFilters({ active_only: false })}
                    >
                        Include completed
                        <X />
                    </Button>
                </div>
            )}
            <Table className="op-record-table">
                <TableHeader>
                    <TableRow>
                        <TableHead>
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    visitFilters({
                                        sort: ascending
                                            ? "reference_desc"
                                            : "reference_asc",
                                    })
                                }
                                disabled={loading}
                                aria-label={`Sort references ${ascending ? "descending" : "ascending"}`}
                            >
                                Document
                                {ascending ? <ArrowUp /> : <ArrowDown />}
                            </Button>
                        </TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Office</TableHead>
                        <TableHead>
                            <span className="sr-only">Open document</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((document) => (
                        <TableRow
                            key={document.id}
                            className="cursor-pointer"
                            data-selected={selected?.id === document.id}
                            onClick={(event) => {
                                if (!loading && !(event.target as HTMLElement).closest("button, a, input, select, textarea, [role=button], [role=checkbox]")) {
                                    onSelect(document, false);
                                }
                            }}
                        >
                            <TableCell>
                                <Button
                                    variant="link"
                                    className="op-reference"
                                    disabled={loading}
                                    onClick={() => onSelect(document, false)}
                                    aria-pressed={selected?.id === document.id}
                                >
                                    {document.reference_number}
                                </Button>
                                <small className="op-row-purpose">
                                    {document.purpose} · Procurement #
                                    {document.procurement_id}
                                </small>
                            </TableCell>
                            <TableCell>
                                <DocumentStatus status={document.status} />
                            </TableCell>
                            <TableCell>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span
                                            tabIndex={0}
                                            className="op-office-label"
                                            title={
                                                document.current_office_name ??
                                                "Not assigned"
                                            }
                                        >
                                            {document.current_office_abbreviation ??
                                                "—"}
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent className="op-live-overlay">
                                        {document.current_office_name ??
                                            "Not assigned"}
                                    </TooltipContent>
                                </Tooltip>
                            </TableCell>
                            <TableCell>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Open overview for ${document.reference_number}`}
                                    disabled={loading}
                                    onClick={() => onSelect(document, true)}
                                >
                                    <ChevronRight />
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {rows.length === 0 && (
                <div className="op-empty">
                    <FolderOpen />
                    <h3>
                        {filters.search
                            ? "No matching documents"
                            : "No documents in this view"}
                    </h3>
                    <p>
                        {filters.search
                            ? "Try another reference or clear your search."
                            : "Choose another year or office to see its documents."}
                    </p>
                    {filters.search && (
                        <Button
                            variant="outline"
                            onClick={() => visitFilters({ search: "" })}
                        >
                            Clear search
                        </Button>
                    )}
                </div>
            )}
            <div className="op-table-footer">
                <span>
                    {documents.from ?? 0}–{documents.to ?? 0} of{" "}
                    {documents.total} documents · {selectedYear}
                </span>
                <div className="op-pagination">
                    <Button
                        size="icon"
                        variant="ghost"
                        disabled={!documents.prev_page_url || loading}
                        aria-label="Previous document page"
                        onClick={() => {
                            if (onPage) onPage(documents.current_page + -1);
                            else if (documents.prev_page_url)
                                router.visit(documents.prev_page_url, {
                                    preserveState: true,
                                    preserveScroll: true,
                                });
                        }}
                    >
                        <ChevronLeft />
                    </Button>
                    <span>
                        {documents.current_page} / {documents.last_page}
                    </span>
                    <Button
                        size="icon"
                        variant="ghost"
                        disabled={!documents.next_page_url || loading}
                        aria-label="Next document page"
                        onClick={() => {
                            if (onPage) onPage(documents.current_page + 1);
                            else if (documents.next_page_url)
                                router.visit(documents.next_page_url, {
                                    preserveState: true,
                                    preserveScroll: true,
                                });
                        }}
                    >
                        <ChevronRight />
                    </Button>
                </div>
            </div>
        </div>
    );
}
