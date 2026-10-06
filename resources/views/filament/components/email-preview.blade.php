{{--
    Explicit inline styles: fi-* utility classes are not guaranteed to be in the compiled panel CSS.
    The sandbox attribute (empty = most restrictive) keeps template markup from running scripts or navigating.
--}}
<iframe
    srcdoc="{{ $html }}"
    sandbox=""
    title="{{ __('Email preview') }}"
    style="display: block; width: 100%; height: 70vh; border: 0; border-radius: 0.75rem; background: #ffffff; box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.1);"
></iframe>
