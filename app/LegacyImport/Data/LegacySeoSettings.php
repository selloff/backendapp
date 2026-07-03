<?php

namespace App\LegacyImport\Data;

final class LegacySeoSettings
{
    /**
     * Production legacy SEO tools settings.
     *
     * @return array{google_analytics: string, sitemap_frequency: string, sitemap_last_modification: string, sitemap_priority: string}
     */
    public static function values(): array
    {
        return [
            'google_analytics' => '<!-- Facebook Pixel Code -->
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=\'2.0\';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,\'script\',
        \'https://connect.facebook.net/en_US/fbevents.js\');
        fbq(\'init\', \'1502634686565632\');
        fbq(\'track\', \'PageView\');
        </script>
        <noscript><img height=\\"1\\" width=\\"1\\" style=\\"display:none\\"
        src=\\"https://www.facebook.com/tr?id=1502634686565632&ev=PageView&noscript=1\\"
        /></noscript>
      <!-- End Facebook Pixel Code -->',
            'sitemap_frequency' => 'none',
            'sitemap_last_modification' => 'none',
            'sitemap_priority' => 'none',
        ];
    }
}
