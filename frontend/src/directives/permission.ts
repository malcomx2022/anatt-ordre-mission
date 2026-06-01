import type { Directive } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { permissionsForRole } from '@/utils/permissions'

function hasPermission(permission: string): boolean {
  const store = useAuthStore()
  return permissionsForRole(store.user?.role).includes(permission as never)
}

export const vPermission: Directive<HTMLElement, string | string[]> = {
  mounted(el, binding) {
    const required = Array.isArray(binding.value) ? binding.value : [binding.value]
    if (!required.some((p) => hasPermission(p))) {
      el.style.display = 'none'
    }
  },
  updated(el, binding) {
    const required = Array.isArray(binding.value) ? binding.value : [binding.value]
    el.style.display = required.some((p) => hasPermission(p)) ? '' : 'none'
  },
}
