<style>
    .notification-page .card { color: #263445; }
    .notification-page .card-header { background-color: #f7f9fb; }
    .notification-page .card h1 { margin-bottom: 0; }
    .notification-page .card nav { display: flex; flex-wrap: wrap; gap: .5rem; }
    .notification-page .card nav a { display: inline-flex; align-items: center; min-height: 44px; padding: .5rem .75rem; color: #17629b; }
    .notification-page .card nav a[aria-current="page"] { color: #263445; font-weight: 700; text-decoration: underline; text-underline-offset: .2em; }
    .notification-page .card :focus-visible { outline: 3px solid #17629b; outline-offset: 3px; }
    .notification-page .card .text-muted { color: #526070 !important; }
    @media (prefers-color-scheme: dark) {
        .notification-page .card { background-color: #202b38; color: #edf2f7; border-color: #929eae; }
        .notification-page .card-header { background-color: #293646; border-color: #929eae; }
        .notification-page .card nav a { color: #8dc9f5; }
        .notification-page .card nav a[aria-current="page"] { color: #edf2f7; }
        .notification-page .card .text-muted { color: #bec9d6 !important; }
        .notification-page .card :focus-visible { outline-color: #8dc9f5; }
    }
</style>
