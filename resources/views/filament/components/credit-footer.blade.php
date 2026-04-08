{{--
    Stili espliciti: le classi fi-* nel render hook spesso non sono nel CSS compilato,
    quindi il markup risultava nero e grande. Questo blocco è sempre piccolo, grigio e centrato.
--}}
@once
<style>
    .newsletter-credit {
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 1.5rem 1rem 0.35rem;
        box-sizing: border-box;
    }

    .newsletter-credit__text {
        margin: 0;
        width: 100%;
        max-width: 100%;
        text-align: center;
        font-size: 0.5625rem;
        line-height: 1.35;
        letter-spacing: 0.02em;
        color: #9ca3af;
    }

    .newsletter-credit__link {
        color: inherit;
        text-decoration: none;
        font-weight: 400;
        opacity: 0.92;
        transition: opacity 0.15s ease, text-decoration-color 0.15s ease;
    }

    .newsletter-credit__link:hover {
        opacity: 1;
        text-decoration: underline;
        text-decoration-color: #9ca3af;
        text-underline-offset: 2px;
    }

    html.dark .newsletter-credit__text,
    html.dark .newsletter-credit__link {
        color: #9ca3af;
    }

    html.dark .newsletter-credit__link:hover {
        text-decoration-color: #9ca3af;
    }
</style>
@endonce

<div class="newsletter-credit">
    <p class="newsletter-credit__text">
        <a
            class="newsletter-credit__link"
            href="https://newsletter.web.ap.it/"
            target="_blank"
            rel="noopener noreferrer"
        >
            {{ __('Made with') }} ♥ {{ __('by Andrea Pollastri') }}
        </a>
    </p>
</div>
