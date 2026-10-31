{{-- Same lockup as the archive masthead; only the second line changes to say which side you are on --}}
<span class="rris-brand">
    <x-brand-mark class="rris-brand-mark" />
    <span class="rris-brand-text">
        <span class="rris-brand-name">RRIS</span>
        <span class="rris-brand-sub">{{ auth()->user()?->workspaceLabel() ?? 'Lecturer portal' }}</span>
    </span>
</span>
