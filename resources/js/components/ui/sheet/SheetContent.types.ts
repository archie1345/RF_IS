import type { DialogContentProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

export interface SheetContentProps extends /* @vue-ignore */ DialogContentProps {
  class?: HTMLAttributes["class"]
  side?: "top" | "right" | "bottom" | "left"
}
