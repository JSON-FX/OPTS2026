# DataTable Architecture Notes

## Overview

Shadcn/UI DataTable is built on **TanStack Table v8** (React Table). It provides a complete table solution with sorting, filtering, pagination, column visibility, and row selection.

## Dependencies

- `@tanstack/react-table` v8.21.3 (already installed ✅)
- Shadcn components: `button`, `checkbox`, `dropdown-menu`, `input`, `table`

## Required Features for All Tables

Every DataTable implementation MUST include:

1. **Column Definitions** (`ColumnDef<T>[]`)
2. **Sorting** via `getSortedRowModel()`
3. **Filtering** via `getFilteredRowModel()`
4. **Pagination** via `getPaginationRowModel()`
5. **Column Visibility Toggle**
6. **Row Selection** with checkboxes
7. **Search/Filter Inputs**
8. **Responsive Overflow** handling

## Standard DataTable Pattern

### 1. Column Definitions

```typescript
import { ColumnDef } from "@tanstack/react-table"
import { Checkbox } from "@/Components/ui/checkbox"
import { Button } from "@/Components/ui/button"
import { DropdownMenu } from "@/Components/ui/dropdown-menu"

export const columns: ColumnDef<YourType>[] = [
  // Selection column
  {
    id: "select",
    header: ({ table }) => (
      <Checkbox
        checked={table.getIsAllPageRowsSelected()}
        onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
        aria-label="Select all"
      />
    ),
    cell: ({ row }) => (
      <Checkbox
        checked={row.getIsSelected()}
        onCheckedChange={(value) => row.toggleSelected(!!value)}
        aria-label="Select row"
      />
    ),
    enableSorting: false,
    enableHiding: false,
  },

  // Sortable column
  {
    accessorKey: "email",
    header: ({ column }) => (
      <Button
        variant="ghost"
        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
      >
        Email
        <ArrowUpDown className="ml-2 h-4 w-4" />
      </Button>
    ),
    cell: ({ row }) => row.getValue("email"),
  },

  // Actions column
  {
    id: "actions",
    enableHiding: false,
    cell: ({ row }) => {
      const item = row.original
      return (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" className="h-8 w-8 p-0">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent>
            <DropdownMenuItem>View</DropdownMenuItem>
            <DropdownMenuItem>Edit</DropdownMenuItem>
            <DropdownMenuItem>Delete</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      )
    },
  },
]
```

### 2. Table Component Setup

```typescript
import {
  ColumnDef,
  ColumnFiltersState,
  SortingState,
  VisibilityState,
  flexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useReactTable,
} from "@tanstack/react-table"

function DataTable<TData>({ columns, data }: { columns: ColumnDef<TData>[], data: TData[] }) {
  const [sorting, setSorting] = React.useState<SortingState>([])
  const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([])
  const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({})
  const [rowSelection, setRowSelection] = React.useState({})

  const table = useReactTable({
    data,
    columns,
    onSortingChange: setSorting,
    onColumnFiltersChange: setColumnFilters,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    onColumnVisibilityChange: setColumnVisibility,
    onRowSelectionChange: setRowSelection,
    state: {
      sorting,
      columnFilters,
      columnVisibility,
      rowSelection,
    },
  })

  return (
    <div className="w-full">
      {/* Toolbar */}
      <div className="flex items-center py-4">
        <Input
          placeholder="Search..."
          value={(table.getColumn("email")?.getFilterValue() as string) ?? ""}
          onChange={(event) =>
            table.getColumn("email")?.setFilterValue(event.target.value)
          }
          className="max-w-sm"
        />
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" className="ml-auto">
              Columns <ChevronDown />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent>
            {table.getAllColumns()
              .filter((column) => column.getCanHide())
              .map((column) => (
                <DropdownMenuCheckboxItem
                  key={column.id}
                  checked={column.getIsVisible()}
                  onCheckedChange={(value) => column.toggleVisibility(!!value)}
                >
                  {column.id}
                </DropdownMenuCheckboxItem>
              ))}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      {/* Table */}
      <div className="overflow-hidden rounded-md border">
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id}>
                {headerGroup.headers.map((header) => (
                  <TableHead key={header.id}>
                    {header.isPlaceholder
                      ? null
                      : flexRender(header.column.columnDef.header, header.getContext())}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow key={row.id} data-state={row.getIsSelected() && "selected"}>
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={columns.length} className="h-24 text-center">
                  No results.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      {/* Pagination */}
      <div className="flex items-center justify-end space-x-2 py-4">
        <div className="text-muted-foreground flex-1 text-sm">
          {table.getFilteredSelectedRowModel().rows.length} of{" "}
          {table.getFilteredRowModel().rows.length} row(s) selected.
        </div>
        <div className="space-x-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage()}
          >
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage()}
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  )
}
```

### 3. Laravel Integration

**For Laravel Paginated Data:**

```php
// Controller
return Inertia::render('Users/Index', [
    'users' => User::paginate(50),
]);
```

```typescript
// React Component
interface Props {
  users: {
    data: User[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// Use users.data for table data
<DataTable columns={columns} data={users.data} />

// For server-side pagination, use Inertia router
<Button onClick={() => router.get(route('users.index', { page: nextPage }))}>
  Next Page
</Button>
```

## Key TanStack Table Hooks

- `useReactTable()` - Main hook to configure table
- `getCoreRowModel()` - Required base model
- `getSortedRowModel()` - Enable sorting
- `getFilteredRowModel()` - Enable filtering
- `getPaginationRowModel()` - Enable pagination
- `flexRender()` - Render cells/headers

## Column Types

### Basic Column
```typescript
{
  accessorKey: "name",
  header: "Name",
  cell: ({ row }) => row.getValue("name"),
}
```

### Sortable Column
```typescript
{
  accessorKey: "email",
  header: ({ column }) => (
    <Button variant="ghost" onClick={() => column.toggleSorting()}>
      Email <ArrowUpDown />
    </Button>
  ),
}
```

### Formatted Column (Currency)
```typescript
{
  accessorKey: "amount",
  header: "Amount",
  cell: ({ row }) => {
    const amount = parseFloat(row.getValue("amount"))
    return new Intl.NumberFormat("en-PH", {
      style: "currency",
      currency: "PHP",
    }).format(amount)
  },
}
```

### Status Badge Column
```typescript
{
  accessorKey: "status",
  header: "Status",
  cell: ({ row }) => (
    <StatusBadge status={row.getValue("status")} />
  ),
}
```

## Responsive Design

```tsx
<div className="overflow-x-auto">
  <Table className="min-w-full">
    {/* Table content */}
  </Table>
</div>
```

## Performance Tips

1. **Memoize columns** - Define columns outside component or use `React.useMemo()`
2. **Virtual scrolling** - For 1000+ rows, use `@tanstack/react-virtual`
3. **Server-side pagination** - For large datasets, paginate on backend
4. **Debounce filters** - Use `useDebouncedCallback` for search inputs

## Reference

- TanStack Table Docs: https://tanstack.com/table/v8
- Shadcn DataTable Example: https://ui.shadcn.com/docs/components/data-table
- Already used in: `resources/js/Pages/Transactions/Index.tsx` (Story 2.10)

---

**Date:** 2024-11-04
**Author:** James (Dev Agent)
**Story:** 0.1.1 - Pre-Migration Setup & Component Installation
