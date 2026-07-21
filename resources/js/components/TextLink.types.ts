import type { LinkComponentBaseProps, Method } from '@inertiajs/core';

export type Props = {
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
};
