<?php

namespace App\Http\Controllers\Sso;

use App\Domain\Iam\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SsoLogoutChainController extends Controller
{
    /**
     * Sequentially redirect the user's browser to each client's `/iam/logout`.
     *
     * Query param `index` selects which application to call next. After the
     * last client is visited the user is redirected to the IAM homepage.
     */
    public function __invoke(Request $request)
    {
        $index = max(0, (int) $request->query('index', 0));  

        $apps = Application::enabled()
            ->get()
            ->filter(fn(Application $a) => ! empty($a->logout_uri))
            ->values();

        // Done — no clients to call or we've finished the chain
        if ($index >= $apps->count()) {
            return redirect('/');
        }

        $app = $apps[$index];
        $logoutUri = $app->logout_uri;

        if (! $logoutUri) {
            return redirect(route('sso.logout.chain', ['index' => $index + 1]));
        }

        // Ask the client to return to the next index after it clears session
        $next = route('sso.logout.chain', ['index' => $index + 1], true);

        $separator = str_contains($logoutUri, '?') ? '&' : '?';
        $target = $logoutUri . $separator . 'post_logout_redirect=' . urlencode($next);

        // Use away() because target is an external URL (client app)
        return redirect()->away($target);
    }
}
