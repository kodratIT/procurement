<div>
    <h2>Lampiran</h2>

    @if ($attachments->isEmpty())
        <p>Tidak ada lampiran.</p>
    @else
        <ul>
            @foreach ($attachments as $attachment)
                <li>
                    <strong>{{ $attachment->original_name }}</strong>
                    — {{ $attachment->mime_type }}, {{ number_format($attachment->size / 1024, 1) }} KB
                </li>
            @endforeach
        </ul>
    @endif
</div>
