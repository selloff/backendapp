<div class="footer">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-block powered-by">
                @if(!empty($branding['contact_address']))
                    <span class="apple-link">{{ $branding['contact_address'] }}</span><br>
                @endif
                {{ $branding['copyright'] }}
            </td>
        </tr>
    </table>
</div>
