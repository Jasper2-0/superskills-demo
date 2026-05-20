export function debounce(fn, ms) {
  let handle = null;
  return function debounced(...args) {
    if (handle !== null) clearTimeout(handle);
    handle = setTimeout(() => {
      handle = null;
      fn(...args);
    }, ms);
  };
}
