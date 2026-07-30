import type { PrimitiveProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import type { SidebarMenuButtonVariants } from ".";

export interface SidebarMenuButtonProps {
  as?: PrimitiveProps["as"]
  asChild?: PrimitiveProps["asChild"]
  variant?: SidebarMenuButtonVariants["variant"]
  size?: SidebarMenuButtonVariants["size"]
  isActive?: boolean
  class?: HTMLAttributes["class"]
}
