/**
 * nq.js — Declarative Server-Driven UI Runtime for nqphp (HTMX-like)
 *
 * Zero dependencies. Native ES module and IIFE compatible.
 *
 * Supported attributes:
 *   data-nq-get="<url>"        : Perform GET request
 *   data-nq-post="<url>"       : Perform POST request
 *   data-nq-put="<url>"        : Perform PUT request
 *   data-nq-delete="<url>"     : Perform DELETE request
 *   data-nq-trigger="<event>"  : Trigger event: click, submit, change, input, or load (default: click for buttons/links, submit for forms, change for select/inputs)
 *   data-nq-target="<css>"     : Target CSS selector to swap content into (default: this element)
 *   data-nq-swap="<strategy>"  : innerHTML (default), outerHTML, beforebegin, afterbegin, beforeend, afterend, none
 *   data-nq-push-url="true"    : Push request URL to browser History (optional)
 *   data-nq-confirm="<msg>"    : Confirm dialogue before dispatching request
 *   data-nq-indicator="<css>"  : CSS selector of loading indicator (adds .nq-loading class)
 *   data-nq-include="<css>"    : CSS selector of extra form/input elements to serialize with request
 */

(function () {
  'use strict';

  const CSRF_COOKIE = 'nqphp_csrf';
  const CSRF_HEADER = 'X-CSRF-Token';
  const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

  function getCsrfToken() {
    if (typeof document === 'undefined') return null;
    const target = `${CSRF_COOKIE}=`;
    const parts = document.cookie ? document.cookie.split(';') : [];
    for (const raw of parts) {
      const c = raw.trim();
      if (c.startsWith(target)) {
        return decodeURIComponent(c.slice(target.length));
      }
    }
    return null;
  }

  function resolveTarget(element, selector) {
    if (!selector || selector === 'this') {
      return element;
    }
    if (selector.startsWith('closest ')) {
      return element.closest(selector.slice(8).trim());
    }
    if (selector.startsWith('find ')) {
      return element.querySelector(selector.slice(5).trim());
    }
    return document.querySelector(selector);
  }

  function applySwap(target, html, strategy) {
    if (!target) return;
    const s = (strategy || 'innerHTML').toLowerCase();
    const parent = target.parentElement || document.body;

    switch (s) {
      case 'outerhtml': {
        const temp = document.createElement('template');
        temp.innerHTML = html.trim();
        const newNodes = Array.from(temp.content.childNodes);
        target.replaceWith(...newNodes);
        newNodes.forEach((node) => {
          if (node.nodeType === Node.ELEMENT_NODE) {
            scan(node);
          }
        });
        return;
      }
      case 'beforebegin':
        target.insertAdjacentHTML('beforebegin', html);
        scan(parent);
        break;
      case 'afterbegin':
        target.insertAdjacentHTML('afterbegin', html);
        scan(target);
        break;
      case 'beforeend':
        target.insertAdjacentHTML('beforeend', html);
        scan(target);
        break;
      case 'afterend':
        target.insertAdjacentHTML('afterend', html);
        scan(parent);
        break;
      case 'none':
        break;
      case 'innerhtml':
      default:
        target.innerHTML = html;
        scan(target);
        break;
    }
  }

  async function handleRequest(element, method, url) {
    const confirmMsg = element.getAttribute('data-nq-confirm');
    if (confirmMsg && !window.confirm(confirmMsg)) {
      return;
    }

    const targetSelector = element.getAttribute('data-nq-target');
    const target = resolveTarget(element, targetSelector);
    const swapStrategy = element.getAttribute('data-nq-swap') || 'innerHTML';
    const indicatorSelector = element.getAttribute('data-nq-indicator');
    const indicator = indicatorSelector ? document.querySelector(indicatorSelector) : null;

    if (indicator) {
      indicator.classList.add('nq-loading');
    }
    element.classList.add('nq-requesting');

    const headers = new Headers();
    headers.set('Accept', 'text/html, application/xhtml+xml');
    headers.set('X-NQPHP-Request', 'true');
    if (targetSelector) {
      headers.set('X-NQPHP-Target', targetSelector);
    }

    const token = getCsrfToken();
    if (!SAFE_METHODS.has(method) && token) {
      headers.set(CSRF_HEADER, token);
    }

    let body = null;
    let requestUrl = url;

    // Collect payload if form or inputs
    if (element.tagName === 'FORM') {
      const formData = new FormData(element);
      if (method === 'GET') {
        const searchParams = new URLSearchParams(formData);
        const sep = requestUrl.includes('?') ? '&' : '?';
        requestUrl += sep + searchParams.toString();
      } else {
        body = formData;
      }
    } else if (element.getAttribute('data-nq-include')) {
      const includeSelector = element.getAttribute('data-nq-include');
      const included = document.querySelectorAll(includeSelector);
      const formData = new FormData();
      included.forEach((el) => {
        if (el.name) {
          formData.append(el.name, el.value);
        }
      });
      if (method === 'GET') {
        const searchParams = new URLSearchParams(formData);
        const sep = requestUrl.includes('?') ? '&' : '?';
        requestUrl += sep + searchParams.toString();
      } else {
        body = formData;
      }
    }

    const eventDetail = { element, method, url: requestUrl, target, headers };
    const beforeEvent = new CustomEvent('nq:beforeRequest', { detail: eventDetail, cancelable: true });
    if (!document.dispatchEvent(beforeEvent)) {
      if (indicator) indicator.classList.remove('nq-loading');
      element.classList.remove('nq-requesting');
      return;
    }

    try {
      const response = await fetch(requestUrl, {
        method,
        headers,
        body
      });

      const responseText = await response.text();

      if (!response.ok) {
        document.dispatchEvent(new CustomEvent('nq:error', {
          detail: { ...eventDetail, status: response.status, responseText }
        }));
      }

      applySwap(target, responseText, swapStrategy);

      if (element.getAttribute('data-nq-push-url') === 'true') {
        window.history.pushState({}, '', requestUrl);
      }

      document.dispatchEvent(new CustomEvent('nq:afterRequest', {
        detail: { ...eventDetail, status: response.status, responseText }
      }));
    } catch (error) {
      document.dispatchEvent(new CustomEvent('nq:error', {
        detail: { ...eventDetail, error }
      }));
    } finally {
      if (indicator) {
        indicator.classList.remove('nq-loading');
      }
      element.classList.remove('nq-requesting');
    }
  }

  function bindElement(el) {
    if (el._nq_bound) return;
    el._nq_bound = true;

    let method = null;
    let url = null;

    if (el.hasAttribute('data-nq-get')) {
      method = 'GET';
      url = el.getAttribute('data-nq-get');
    } else if (el.hasAttribute('data-nq-post')) {
      method = 'POST';
      url = el.getAttribute('data-nq-post');
    } else if (el.hasAttribute('data-nq-put')) {
      method = 'PUT';
      url = el.getAttribute('data-nq-put');
    } else if (el.hasAttribute('data-nq-delete')) {
      method = 'DELETE';
      url = el.getAttribute('data-nq-delete');
    }

    if (!method || !url) return;

    let trigger = el.getAttribute('data-nq-trigger');
    if (!trigger) {
      if (el.tagName === 'FORM') {
        trigger = 'submit';
      } else if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
        trigger = 'change';
      } else {
        trigger = 'click';
      }
    }

    if (trigger === 'load') {
      handleRequest(el, method, url);
      return;
    }

    el.addEventListener(trigger, (evt) => {
      if (el.tagName === 'A' || el.tagName === 'FORM' || el.tagName === 'BUTTON') {
        evt.preventDefault();
      }
      handleRequest(el, method, url);
    });
  }

  function scan(root = document) {
    const selector = '[data-nq-get], [data-nq-post], [data-nq-put], [data-nq-delete]';
    if (root.matches && root.matches(selector)) {
      bindElement(root);
    }
    const elements = root.querySelectorAll(selector);
    elements.forEach(bindElement);
  }

  // Auto-init on DOM ready
  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => scan());
    } else {
      scan();
    }
  }

  const nq = {
    scan,
    csrf: getCsrfToken,
    resolveTarget,
    applySwap,
    handleRequest
  };

  if (typeof window !== 'undefined') {
    window.nq = nq;
  }
})();
