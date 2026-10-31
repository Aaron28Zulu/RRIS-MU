{{-- Same institutional trail as the archive's utility bar, so both sides of RRIS read as one system --}}
<p class="rris-topbar-context">
    <span>Mulungushi University</span>
    <span class="rris-topbar-context-sep" aria-hidden="true">/</span>
    <span class="rris-topbar-context-here">{{ auth()->user()?->workspaceLabel() ?? 'Lecturer portal' }}</span>
</p>
