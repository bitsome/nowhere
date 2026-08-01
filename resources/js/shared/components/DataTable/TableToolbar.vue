<template>
    <div class="datatable-toolbar">
        <div class="datatable-toolbar__main">
            <slot />
        </div>

        <div class="datatable-toolbar__search">
            <slot name="search" />
        </div>

        <div class="datatable-toolbar__filter">
            <slot name="filter" />
        </div>

        <div class="datatable-toolbar__actions">
            <slot name="actions" />
        </div>
    </div>
</template>
