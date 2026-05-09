<script setup lang="ts">
import { computed, ref } from 'vue';
import { ClipboardList, PencilLine, UserRoundCog } from 'lucide-vue-next';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import ManagementTablePanel from '@/components/shared/ManagementTablePanel.vue';
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
import type { Group } from '@/types/group';
import type { TableColumn, TableRow } from '@/types/management';

const props = defineProps<{
    groups: Group[];
}>();

const emit = defineEmits<{
    create: [payload: { name: string; description: string | null }];
    update: [payload: { id: string; name: string; description: string | null }];
    delete: [id: string];
}>();

const isFormOpen = ref(false);
const editingId = ref<string | null>(null);
const form = ref({
    name: '',
    description: '',
});

const columns: TableColumn[] = [
    { key: 'name', label: 'Group' },
    { key: 'description', label: 'Description' },
];

const rows = computed<TableRow[]>(() =>
    props.groups.map((group) => ({
        id: group.id,
        name: group.name,
        description: group.description ?? '-',
    })),
);

function resetForm() {
    editingId.value = null;
    form.value = {
        name: '',
        description: '',
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
        description: row.description === '-' ? '' : String(row.description ?? ''),
    };
    isFormOpen.value = true;
}

function submit() {
    const payload = {
        name: form.value.name,
        description: form.value.description || null,
    };

    if (editingId.value) {
        emit('update', {
            id: editingId.value,
            ...payload,
        });
    } else {
        emit('create', payload);
    }

    isFormOpen.value = false;
    resetForm();
}
</script>

<template>
    <div>
        <ManagementTablePanel
            eyebrow="Admin panel"
            title="Group Management"
            description="Manage athlete groups with the same reusable CRUD table and dialog pattern used across the admin workspace."
            create-label="New group"
            table-title="Group roster"
            table-description="A reusable group table designed for expansion without duplicating layout code."
            :columns="columns"
            :rows="rows"
            empty-text="No groups available yet."
            action-label="Action"
            @create="openCreate"
        >
            <template #row-actions="{ row }">
                <ActionButtonsRow>
                    <Button variant="outline" class="gap-2" @click="openEdit(row)">
                        <PencilLine class="size-4" />
                        Edit
                    </Button>
                    <Button variant="destructive" @click="emit('delete', row.id)">
                        Remove
                    </Button>
                </ActionButtonsRow>
            </template>
        </ManagementTablePanel>

        <Dialog v-model:open="isFormOpen">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <UserRoundCog class="size-5" />
                        {{ editingId ? 'Edit group' : 'Create group' }}
                    </DialogTitle>
                    <DialogDescription>
                        This matches the branch management flow so future admin modules can stay visually and structurally consistent.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-2">
                    <div class="grid gap-2">
                        <Label for="group-name">Group name</Label>
                        <div class="relative">
                            <ClipboardList class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input id="group-name" v-model="form.name" class="pl-9" placeholder="Junior Sparring" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="group-description">Description</Label>
                        <Input id="group-description" v-model="form.description" placeholder="Fundamental technique and sparring preparation." />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="isFormOpen = false">Cancel</Button>
                    <Button @click="submit">
                        {{ editingId ? 'Save changes' : 'Create group' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>


