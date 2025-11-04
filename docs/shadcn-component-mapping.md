# Shadcn/UI Component Mapping Guide

## Overview

This document maps legacy custom components to their shadcn/UI equivalents for the OPTS2026 UI migration (Epic 0.1).

**Purpose:** Provide developers with a reference when migrating pages from legacy components to shadcn/UI.

**Status:** Foundation setup complete (Story 0.1.1)

---

## Component Mapping Table

| Legacy Component | Shadcn Component | Variant/Props | Import Path Change | Notes |
|-----------------|------------------|---------------|-------------------|-------|
| **TextInput** | `Input` | - | `@/Components/TextInput` → `@/Components/ui/input` | Direct replacement, same props |
| **PrimaryButton** | `Button` | `variant="default"` | `@/Components/PrimaryButton` → `@/Components/ui/button` | Add variant prop |
| **SecondaryButton** | `Button` | `variant="secondary"` | `@/Components/SecondaryButton` → `@/Components/ui/button` | Add variant prop |
| **DangerButton** | `Button` | `variant="destructive"` | `@/Components/DangerButton` → `@/Components/ui/button` | Add variant prop |
| **InputLabel** | `Label` | - | `@/Components/InputLabel` → `@/Components/ui/label` | Direct replacement |
| **InputError** | `FormMessage` | Must wrap in `FormItem` | `@/Components/InputError` → `@/Components/ui/form` | Requires form context |
| **Modal** | `Dialog` | Different prop structure | `@/Components/Modal` → `@/Components/ui/dialog` | See Dialog migration pattern |
| **Dropdown** | `DropdownMenu` | Already migrated ✅ | Already using `@/Components/ui/dropdown-menu` | No changes needed |
| **ResponsiveNavLink** | `NavigationMenu` + `Sheet` | Mobile uses Sheet | New components | Sheet for mobile, NavigationMenu for desktop |
| **Checkbox** | `Checkbox` | Already exists | `@/Components/Checkbox` → `@/Components/ui/checkbox` | Radix-based implementation |
| **StatusBadge** | `Badge` | Already migrated ✅ | Already using `@/Components/ui/badge` | No changes needed |

---

## Import Path Migration Pattern

### Before (Mixed Pattern - Inconsistent)
```typescript
import TextInput from '@/Components/TextInput'
import InputLabel from '@/Components/InputLabel'
import PrimaryButton from '@/Components/PrimaryButton'
import { Button } from '@/Components/ui/button' // Already shadcn
```

### After (Consistent Pattern)
```typescript
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Button } from '@/Components/ui/button'
```

**Key Change:** All imports use named exports from `@/Components/ui/*`

---

## Detailed Migration Patterns

### 1. Button Migration

**Before:**
```tsx
import PrimaryButton from '@/Components/PrimaryButton'
import SecondaryButton from '@/Components/SecondaryButton'
import DangerButton from '@/Components/DangerButton'

<PrimaryButton onClick={handleSubmit}>Save</PrimaryButton>
<SecondaryButton onClick={handleCancel}>Cancel</SecondaryButton>
<DangerButton onClick={handleDelete}>Delete</DangerButton>
```

**After:**
```tsx
import { Button } from '@/Components/ui/button'

<Button variant="default" onClick={handleSubmit}>Save</Button>
<Button variant="secondary" onClick={handleCancel}>Cancel</Button>
<Button variant="destructive" onClick={handleDelete}>Delete</Button>
```

**Button Variants:**
- `default` - Primary action (blue)
- `secondary` - Secondary action (gray)
- `destructive` - Dangerous action (red)
- `outline` - Outlined style
- `ghost` - Transparent background
- `link` - Styled as link

**Button Sizes:**
- `default` - Standard size
- `sm` - Small
- `lg` - Large
- `icon` - Square icon button

---

### 2. Input & Label Migration

**Before:**
```tsx
import InputLabel from '@/Components/InputLabel'
import TextInput from '@/Components/TextInput'
import InputError from '@/Components/InputError'

<div>
  <InputLabel htmlFor="email" value="Email" />
  <TextInput
    id="email"
    type="email"
    value={data.email}
    onChange={(e) => setData('email', e.target.value)}
    className="mt-1 block w-full"
  />
  <InputError message={errors.email} className="mt-2" />
</div>
```

**After (Simple):**
```tsx
import { Label } from '@/Components/ui/label'
import { Input } from '@/Components/ui/input'

<div className="space-y-2">
  <Label htmlFor="email">Email</Label>
  <Input
    id="email"
    type="email"
    value={data.email}
    onChange={(e) => setData('email', e.target.value)}
  />
  {errors.email && (
    <p className="text-sm text-destructive">{errors.email}</p>
  )}
</div>
```

**After (With Form Components - Recommended):**
```tsx
import { Form, FormField, FormItem, FormLabel, FormControl, FormMessage } from '@/Components/ui/form'
import { Input } from '@/Components/ui/input'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'

const formSchema = z.object({
  email: z.string().email(),
})

function MyForm() {
  const form = useForm({
    resolver: zodResolver(formSchema),
    defaultValues: { email: '' },
  })

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)}>
        <FormField
          control={form.control}
          name="email"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Email</FormLabel>
              <FormControl>
                <Input type="email" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </form>
    </Form>
  )
}
```

---

### 3. Dialog (Modal) Migration

**Before:**
```tsx
import Modal from '@/Components/Modal'

<Modal show={isOpen} onClose={handleClose}>
  <h2>Confirm Delete</h2>
  <p>Are you sure?</p>
  <div>
    <PrimaryButton onClick={handleConfirm}>Yes</PrimaryButton>
    <SecondaryButton onClick={handleClose}>Cancel</SecondaryButton>
  </div>
</Modal>
```

**After:**
```tsx
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'

<Dialog open={isOpen} onOpenChange={setIsOpen}>
  <DialogContent>
    <DialogHeader>
      <DialogTitle>Confirm Delete</DialogTitle>
      <DialogDescription>
        Are you sure you want to delete this item?
      </DialogDescription>
    </DialogHeader>
    <DialogFooter>
      <Button variant="secondary" onClick={() => setIsOpen(false)}>
        Cancel
      </Button>
      <Button variant="destructive" onClick={handleConfirm}>
        Yes, Delete
      </Button>
    </DialogFooter>
  </DialogContent>
</Dialog>
```

**Key Differences:**
- Use `open`/`onOpenChange` instead of `show`/`onClose`
- Structured with Header/Content/Footer components
- More semantic HTML structure

---

### 4. AlertDialog (Confirmation) Pattern

**For destructive actions, use AlertDialog instead of Dialog:**

```tsx
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/Components/ui/alert-dialog'

<AlertDialog>
  <AlertDialogTrigger asChild>
    <Button variant="destructive">Delete</Button>
  </AlertDialogTrigger>
  <AlertDialogContent>
    <AlertDialogHeader>
      <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
      <AlertDialogDescription>
        This action cannot be undone. This will permanently delete the item.
      </AlertDialogDescription>
    </AlertDialogHeader>
    <AlertDialogFooter>
      <AlertDialogCancel>Cancel</AlertDialogCancel>
      <AlertDialogAction onClick={handleDelete}>Delete</AlertDialogAction>
    </AlertDialogFooter>
  </AlertDialogContent>
</AlertDialog>
```

---

### 5. Form with Inertia Integration

**Complete form pattern with Inertia useForm:**

```tsx
import { useForm } from '@inertiajs/react'
import { FormEventHandler } from 'react'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'

export default function CreateUser() {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
  })

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route('users.store'))
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Create User</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="name">Name</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              required
            />
            {errors.name && (
              <p className="text-sm text-destructive">{errors.name}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              required
            />
            {errors.email && (
              <p className="text-sm text-destructive">{errors.email}</p>
            )}
          </div>

          <Button type="submit" disabled={processing}>
            {processing ? 'Creating...' : 'Create User'}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
```

---

### 6. DataTable Migration

**For any table in the application, use DataTable pattern:**

See `docs/datatable-architecture-notes.md` for complete implementation.

**Key Points:**
- Use `@tanstack/react-table` (already installed)
- Define typed columns with `ColumnDef<YourType>[]`
- Include selection, sorting, filtering, pagination
- Use shadcn Table components for rendering

---

## Props Interface Changes

### Input

**Before (TextInput):**
```typescript
interface TextInputProps {
  type?: string
  className?: string
  isFocused?: boolean
  // ...other HTML input props
}
```

**After (Input):**
```typescript
// Extends React.ComponentPropsWithoutRef<"input">
// All standard input props supported
<Input type="email" className="..." />
```

### Button

**Before (PrimaryButton):**
```typescript
interface PrimaryButtonProps {
  className?: string
  disabled?: boolean
  children: React.ReactNode
}
```

**After (Button):**
```typescript
interface ButtonProps {
  variant?: "default" | "destructive" | "outline" | "secondary" | "ghost" | "link"
  size?: "default" | "sm" | "lg" | "icon"
  asChild?: boolean // Allows composition with Slot
  // ...extends button HTML props
}
```

---

## Common Patterns

### Card Layout
```tsx
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card'

<Card>
  <CardHeader>
    <CardTitle>Title</CardTitle>
    <CardDescription>Description</CardDescription>
  </CardHeader>
  <CardContent>
    {/* Content */}
  </CardContent>
  <CardFooter>
    {/* Footer actions */}
  </CardFooter>
</Card>
```

### Alert Messages
```tsx
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { AlertCircle } from 'lucide-react'

<Alert variant="destructive">
  <AlertCircle className="h-4 w-4" />
  <AlertTitle>Error</AlertTitle>
  <AlertDescription>Something went wrong.</AlertDescription>
</Alert>
```

### Separator
```tsx
import { Separator } from '@/Components/ui/separator'

<div>
  <h2>Section 1</h2>
  <Separator className="my-4" />
  <h2>Section 2</h2>
</div>
```

---

## TypeScript Tips

### Import all at once
```typescript
import {
  Button,
  Input,
  Label,
  Card,
  CardContent,
  CardHeader,
  Dialog,
  DialogContent,
} from '@/Components/ui'
```

**Note:** Requires index.ts barrel export in `@/Components/ui/index.ts`

### Type-safe form data
```typescript
import { useForm } from '@inertiajs/react'

interface UserFormData {
  name: string
  email: string
  role_id: number
}

const { data, setData, post, errors } = useForm<UserFormData>({
  name: '',
  email: '',
  role_id: 1,
})
```

---

## Migration Checklist

When migrating a page:

- [ ] Update all button imports to use `Button` with variants
- [ ] Replace `TextInput` with `Input`
- [ ] Replace `InputLabel` with `Label`
- [ ] Update error display (use `FormMessage` or simple `<p>` with `text-destructive`)
- [ ] Replace `Modal` with `Dialog` or `AlertDialog`
- [ ] Wrap forms in `Card` for consistent styling
- [ ] Use `Separator` for visual sections
- [ ] Add proper `space-y-*` classes for vertical spacing
- [ ] Ensure all imports use `@/Components/ui/*` pattern
- [ ] Test form submission with Inertia
- [ ] Verify TypeScript compiles without errors

---

## Resources

- **Shadcn/UI Docs:** https://ui.shadcn.com/docs/components
- **TanStack Table Docs:** https://tanstack.com/table/v8
- **Radix UI Docs:** https://www.radix-ui.com/primitives
- **Lucide Icons:** https://lucide.dev/icons/

---

**Document Version:** 1.0
**Last Updated:** 2024-11-04
**Author:** James (Dev Agent)
**Story:** 0.1.1 - Pre-Migration Setup & Component Installation
