export function scrollToSelector(selector) {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      document.querySelector(selector)?.scrollIntoView({ behavior: 'smooth' })
    })
  })
}