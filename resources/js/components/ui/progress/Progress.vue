<script setup lang="ts">
import type { ProgressRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { computed } from "vue"
import { ProgressIndicator, ProgressRoot, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ProgressRootProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardProps(delegatedProps)

const modelValue = computed(() => {
  if (typeof props.modelValue !== "number" || Number.isNaN(props.modelValue)) {
    return 0
  }

  return Math.min(Math.max(props.modelValue, 0), 100)
})
</script>

<template>
  <ProgressRoot
    data-slot="progress"
    v-bind="forwarded"
    :class="cn('bg-primary/20 relative h-2 w-full overflow-hidden rounded-full', props.class)"
  >
    <ProgressIndicator
      data-slot="progress-indicator"
      class="bg-primary h-full w-full flex-1 transition-all"
      :style="`transform: translateX(-${100 - modelValue}%);`"
    />
  </ProgressRoot>
</template>
