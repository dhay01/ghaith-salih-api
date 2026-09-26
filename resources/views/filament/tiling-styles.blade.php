{{--
    Filament ships its own prefixed design system and no general Tailwind
    utility layer, and this panel registers no custom theme — so class names
    like `flex`, `w-48` or `bg-gray-200` resolve to nothing here. (The progress
    bar that used them rendered as a zero-height, transparent div.) These rules
    are plain CSS, coloured from the palette Filament does publish as custom
    properties, so they follow the panel's theme and its dark mode.
--}}
<style>
    .tiling { width: 14rem; }
    .tiling--wide { width: 100%; }

    .tiling__head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.5rem;
    }

    /* Two lines rather than one: the tail of the line is the moving part
       ("... 1,420 of 2,194"), so an ellipsis cuts off the only bit that proves
       anything is happening. */
    .tiling__label {
        font-size: 0.75rem;
        line-height: 1.1rem;
        font-weight: 500;
        color: var(--gray-600);
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
    }

    .tiling--wide .tiling__label { -webkit-line-clamp: 1; }

    .tiling--wide .tiling__label { font-size: 0.875rem; }
    .tiling--working .tiling__label { color: var(--warning-600); }
    .tiling--ready   .tiling__label { color: var(--success-600); }
    .tiling--failed  .tiling__label { color: var(--danger-600); }

    .tiling__percent {
        flex-shrink: 0;
        font-size: 0.75rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: var(--gray-500);
    }

    .tiling__track {
        height: 0.375rem;
        margin-top: 0.375rem;
        border-radius: 9999px;
        background: var(--gray-200);
        overflow: hidden;
    }

    .tiling--wide .tiling__track { height: 0.5rem; }

    .tiling__fill {
        height: 100%;
        border-radius: 9999px;
        background: var(--primary-500);
        transition: width 0.5s ease;
    }

    .tiling--ready .tiling__fill { background: var(--success-500); }

    /* Queued has no percentage to show yet, so the stub pulses rather than
       sitting still at zero and reading as a stalled job. */
    .tiling--queued .tiling__fill { animation: tiling-pulse 1.6s ease-in-out infinite; }

    @keyframes tiling-pulse {
        50% { opacity: 0.4; }
    }

    @media (prefers-reduced-motion: reduce) {
        .tiling__fill { transition: none; animation: none; }
    }

    .tiling__note {
        margin-top: 0.375rem;
        font-size: 0.75rem;
        line-height: 1.05rem;
        color: var(--gray-500);
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        overflow: hidden;
    }

    .tiling__note--error { color: var(--danger-600); }

    .dark .tiling__label { color: var(--gray-300); }
    .dark .tiling--working .tiling__label { color: var(--warning-400); }
    .dark .tiling--ready   .tiling__label { color: var(--success-400); }
    .dark .tiling--failed  .tiling__label { color: var(--danger-400); }
    .dark .tiling__percent { color: var(--gray-400); }
    .dark .tiling__track { background: var(--gray-700); }
    .dark .tiling__note { color: var(--gray-400); }
    .dark .tiling__note--error { color: var(--danger-400); }
</style>
