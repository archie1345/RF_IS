<script setup lang="ts">
import { computed, ref } from 'vue';
import { MapPin, PencilLine, UserRoundCog } from 'lucide-vue-next';
import CrudManagementPanel from '@/components/admin/CrudManagementPanel.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Branch } from '@/types/branch';
import type { TableColumn, TableRow } from '@/types/mvp';

const props = defineProps<{
    branches: Branch[];
}>();

const emit = defineEmits<{
    create: [payload: { name: string; location: string }];
    update: [payload: { id: string; name: string; location: string }];
    delete: [id: string];
}>();

const isFormOpen = ref(false);
const editingId = ref<string | null>(null);
const form = ref({
    name: '',
    location: '',
});

const columns: TableColumn[] = [
    { key: 'name', label: 'Branch' },
    { key: 'location', label: 'Location' },
];

const rows = computed<TableRow[]>(() =>
    props.branches.map((branch) => ({
        id: branch.id,
        name: branch.name,
        location: branch.location,
    })),
);

function resetForm() {
    editingId.value = null;
    form.value = {
        name: '',
        location: '',
    };
}

function openCreate() {
    resetForm();
    isFormOpen.value = true;
}

function openEdit(row: TableRow) {
    editingId.value = row.id;
    form.value = {
        name: String(row.name ?? ''),
        location: String(row.location ?? ''),
    };
    isFormOpen.value = true;
}

function submit() {
    if (editingId.value) {
        emit('update', {
            id: editingId.value,
            name: form.value.name,
            location: form.value.location,
        });
    } else {
        emit('create', {
            name: form.value.name,
            location: form.value.location,
        });
    }

    isFormOpen.value = false;
    resetForm();
}
</script>

<template>
    <div>
        <CrudManagementPanel
            eyebrow="Admin panel"
            title="Branch Management"
            description="Manage operational branches with the same reusable management pattern used across admin tools."
            create-label="New branch"
            table-title="Branch roster"
            table-description="A reusable branch table ready for add, update, and delete flows."
            :columns="columns"
            :rows="rows"
            empty-text="No branches available yet."
            action-label="Action"
            @create="openCreate"
        >
            <template #row-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <Button variant="outline" class="gap-2" @click="openEdit(row)">
                        <PencilLine class="size-4" />
                        Edit
                    </Button>
                    <Button variant="destructive" @click="emit('delete', row.id)">
                        Remove
                    </Button>
                </div>
            </template>
        </CrudManagementPanel>

        <Dialog v-model:open="isFormOpen">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <UserRoundCog class="size-5" />
                        {{ editingId ? 'Edit branch' : 'Create branch' }}
                    </DialogTitle>
                    <DialogDescription>
                        This dialog is intentionally parallel to the account flow so branch and group management can expand without duplicating table markup.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-2">
                    <div class="grid gap-2">
                        <Label for="branch-name">Branch name</Label>
                        <Input id="branch-name" v-model="form.name" placeholder="Central Dojang" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="branch-location">Location</Label>
                        <div class="relative">
                            <MapPin class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input id="branch-location" v-model="form.location" class="pl-9" placeholder="Jakarta Selatan" />
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="isFormOpen = false">Cancel</Button>
                    <Button @click="submit">
                        {{ editingId ? 'Save changes' : 'Create branch' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

