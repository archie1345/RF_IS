import type { PrimitiveProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import type { ButtonVariants } from ".";

export interface Props {
  as?: PrimitiveProps["as"]
  asChild?: PrimitiveProps["asChild"]
  variant?: ButtonVariants["variant"]
  size?: ButtonVariants["size"]
  class?: HTMLAttributes["class"]
}
