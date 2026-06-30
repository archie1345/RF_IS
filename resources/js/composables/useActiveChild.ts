import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Auth, ParentChild } from '@/types/auth';

type SharedProps = {
    auth?: Partial<Auth>;
};

export function useActiveChild() {
    const page = usePage<SharedProps>();
    const children = computed<ParentChild[]>(() => page.props.auth?.children ?? []);
    const activeChild = computed<ParentChild | null>(() => page.props.auth?.activeChild ?? null);

    return {
        children,
        activeChild,
        hasChildren: computed(() => children.value.length > 0),
        hasActiveChild: computed(() => activeChild.value !== null),
    };
}
