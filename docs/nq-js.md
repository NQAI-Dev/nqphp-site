# Declarative UI & nq.js

`nqphp` includes `nq.js` — a lightweight (zero-dependency, ~4KB) declarative JavaScript runtime inspired by HTMX. It turns ordinary HTML elements into interactive components without heavy SPA frameworks or build steps.

## Quick Start

Include `nq.js` in your HTML layout:

```html
<script src="/_nqphp/js/nq.js" defer></script>
```

Or using the PHP `JsRuntime` helper from your controller:

```php
$js = $this->kernel->js();
$url = $js->nqUrl(); // Returns "/_nqphp/js/nq.js"
```

## Supported Attributes

| Attribute | Description | Default |
|-----------|-------------|---------|
| `data-nq-get="<url>"` | Performs an asynchronous GET request | — |
| `data-nq-post="<url>"` | Performs an asynchronous POST request | — |
| `data-nq-put="<url>"` | Performs an asynchronous PUT request | — |
| `data-nq-delete="<url>"` | Performs an asynchronous DELETE request | — |
| `data-nq-target="<selector>"` | CSS selector of the DOM element to update | `this` (self) |
| `data-nq-swap="<strategy>"` | How to insert HTML: `innerHTML`, `outerHTML`, `beforebegin`, `afterbegin`, `beforeend`, `afterend` | `innerHTML` |
| `data-nq-trigger="<event>"` | Event that triggers the action: `click`, `submit`, `change`, `input`, `load` | Contextual |
| `data-nq-push-url="true"` | Pushes the request URL into browser History | `false` |
| `data-nq-confirm="<text>"` | Displays native confirmation prompt before request | — |
| `data-nq-indicator="<selector>"` | CSS selector of an element that receives `.nq-loading` class during flight | — |
| `data-nq-include="<selector>"` | CSS selector of extra inputs/forms to serialize into the request | — |

## Examples

### 1. Click to Load Content

```html
<button 
    data-nq-get="/api/stats" 
    data-nq-target="#stats-box" 
    data-nq-swap="innerHTML">
    Refresh Stats
</button>

<div id="stats-box">Loading...</div>
```

### 2. Form Submission with Inline Validation

```html
<form 
    data-nq-post="/contact" 
    data-nq-target="#contact-form-container" 
    data-nq-swap="outerHTML">
    
    <input type="email" name="email" required>
    <button type="submit">Submit</button>
</form>
```

### 3. Automatic CSRF Protection

When sending state-changing requests (`POST`, `PUT`, `DELETE`), `nq.js` automatically inspects the `nqphp_csrf` cookie and sends the `X-CSRF-Token` header. No manual token embedding is required.

### 4. Target Selectors

In addition to standard CSS selectors (`#id`, `.class`), `data-nq-target` supports special keywords:
- `this` — Targets the element itself.
- `closest <selector>` — Traverses ancestors up to the matching selector (e.g. `closest tr` to remove a table row).
- `find <selector>` — Searches inside descendants of the element.

## DOM Events

`nq.js` dispatches lifecycle events on `document`:
- `nq:beforeRequest` — Fired before dispatching. Call `event.preventDefault()` to cancel.
- `nq:afterRequest` — Fired after swap completes.
- `nq:error` — Fired on network failure or HTTP 4xx/5xx status.
