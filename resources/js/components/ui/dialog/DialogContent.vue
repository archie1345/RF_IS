<script setup lang="ts">
import type { DialogContentProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { X } from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogPortal,
    useForwardPropsEmits,
} from 'reka-ui';
import { cn } from '@/lib/utils';
import DialogOverlay from './DialogOverlay.vue';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<
        /* @vue-ignore */ DialogContentProps & {
            class?: HTMLAttributes['class'];
            showCloseButton?: boolean;
        }
    >(),
    {
        showCloseButton: true,
    },
);
const emits = defineEmits();

const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <DialogPortal>
        <DialogOverlay />
        <DialogContent
            data-slot="dialog-content"
            v-bind="{ ...$attrs, ...forwarded }"
            :class="
                cn(
                    'bg-card data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-[50%] left-[50%] z-50 grid max-h-[calc(100dvh-1rem)] w-[calc(100%-1rem)] max-w-none translate-x-[-50%] translate-y-[-50%] gap-4 overflow-y-auto overscroll-contain rounded-xl border border-border/70 p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-xl duration-200 sm:max-h-[90dvh] sm:max-w-xl sm:p-6',
                    props.class,
                )
            "
        >
            <slot />

            <DialogClose
                v-if="showCloseButton"
                data-slot="dialog-close"
                class="ring-offset-background focus:ring-ring data-[state=open]:bg-accent data-[state=open]:text-muted-foreground absolute top-3 right-3 flex size-10 items-center justify-center rounded-full border bg-card/95 opacity-80 shadow-sm transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none sm:top-4 sm:right-4 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
            >
                <X />
                <span class="sr-only">Tutup</span>
            </DialogClose>
        </DialogContent>
    </DialogPortal>
</template>
