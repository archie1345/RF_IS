<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';
import PageSection from './PageSection.vue';

const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        eyebrow?: string;
        defaultOpen?: boolean;
        collapseLabel?: string;
        expandLabel?: string;
    }>(),
    {
        defaultOpen: true,
        collapseLabel: 'Collapse',
        expandLabel: 'Expand',
    },
);

const open = ref(props.defaultOpen);
</script>

<template>
    <Collapsible v-model:open="open">
        <PageSection :title="title" :description="description" :eyebrow="eyebrow">
            <template #actions>
                <div class="flex items-center gap-2">
                    <slot name="actions" />
                    <CollapsibleTrigger as-child>
                        <Button type="button" variant="outline" size="sm">
                            {{ open ? collapseLabel : expandLabel }}
                            <ChevronDown class="ml-2 h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" />
                        </Button>
                    </CollapsibleTrigger>
                </div>
            </template>
            <CollapsibleContent>
                <slot />
            </CollapsibleContent>
        </PageSection>
    </Collapsible>
</template>
