<template>
    <div class="card-skeleton" aria-hidden="true">
        <div class="card-skeleton__head">
            <div class="card-skeleton__line card-skeleton__line--wide" />
            <div class="card-skeleton__line card-skeleton__line--short" />
        </div>
        <div class="card-skeleton__line" />
        <div class="card-skeleton__row">
            <div class="card-skeleton__chip" />
            <div class="card-skeleton__chip" />
        </div>
    </div>
</template>

<style scoped>
.card-skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
    overflow: hidden;
}

.card-skeleton__head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
}

.card-skeleton__line {
    height: 14px;
    border-radius: 7px;
    background: linear-gradient(90deg, var(--border) 25%, rgba(128, 128, 128, 0.12) 50%, var(--border) 75%);
    background-size: 200% 100%;
    animation: card-shimmer 1.4s infinite;
}

.card-skeleton__line--wide {
    flex: 1;
    height: 18px;
}

.card-skeleton__line--short {
    width: 72px;
    height: 20px;
    flex-shrink: 0;
}

.card-skeleton__row {
    display: flex;
    gap: 8px;
}

.card-skeleton__chip {
    width: 56px;
    height: 16px;
    border-radius: 8px;
    background: var(--border);
    animation: card-shimmer 1.4s infinite;
}

@keyframes card-shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .card-skeleton__line,
    .card-skeleton__chip {
        animation: none;
    }
}
</style>
