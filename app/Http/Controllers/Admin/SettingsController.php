<?php

namespace Azuriom\Http\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\ActionLog;
use Azuriom\Models\Image;
use Azuriom\Models\Setting;
use Azuriom\Support\Optimizer;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * The supported mail drivers.
     *
     * @var array
     */
    private $mailEncryptionTypes = [
        'tls' => 'TLS',
        'ssl' => 'SSL',
    ];

    /**
     * The supported mail drivers.
     *
     * @var array
     */
    private $mailMailers = [
        'smtp' => 'SMTP',
        'sendmail' => 'Sendmail',
    ];

    /**
     * The supported hash algorithms.
     *
     * @var array
     */
    private $hashAlgorithms = [
        'bcrypt' => 'Bcrypt',
        'argon' => 'Argon2i',
        'argon2id' => 'Argon2id',
    ];

    /**
     * The hash algorithms PHP constants.
     *
     * @var array
     */
    private $hashCompatibility = [
        'argon' => 'PASSWORD_ARGON2I',
        'argon2id' => 'PASSWORD_ARGON2ID',
    ];

    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private $app;

    /**
     * The application cache.
     *
     * @var \Illuminate\Cache\Repository
     */
    private $cache;

    /**
     * The Azuriom optimizer.
     *
     * @var \Azuriom\Support\Optimizer
     */
    private $optimizer;

    /**
     * Create a new controller instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Illuminate\Cache\Repository  $cache
     * @param  \Azuriom\Support\Optimizer  $optimizer
     */
    public function __construct(Application $app, Cache $cache, Optimizer $optimizer)
    {
        $this->app = $app;
        $this->cache = $cache;
        $this->optimizer = $optimizer;
    }

    /**
     * Show the application settings.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.settings.index', [
            'images' => Image::all(),
            'icon' => setting('icon'),
            'logo' => setting('logo'),
            'background' => setting('background'),
            'locales' => $this->getAvailableLocales(),
            'timezones' => timezone_identifiers_list(),
            'currentTimezone' => config('app.timezone'),
            'copyright' => setting('copyright'),
            'conditions' => setting('conditions'),
            'money' => setting('money'),
            'register' => setting('register', true),
            'authApi' => setting('auth-api', false),
            'minecraftVerification' => setting('game-type') === 'mc-online',
        ]);
    }

    /**
     * Update the application settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request)
    {
        Setting::updateSettings($this->validate($request, [
            'name' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'timezone' => ['required', 'timezone'],
            'copyright' => ['nullable', 'string', 'max:150'],
            'conditions' => ['nullable', 'url', 'max:150'],
            'locale' => ['required', 'string', Rule::in($this->getAvailableLocaleCodes())],
            'icon' => ['nullable', 'exists:images,file'],
            'logo' => ['nullable', 'exists:images,file'],
            'background' => ['nullable', 'exists:images,file'],
            'money' => ['required', 'string', 'max:15'],
        ]) + [
            'register' => $request->has('register'),
            'auth-api' => $request->has('auth-api'),
            'game-type' => $request->has('minecraft-verification') ? 'mc-online' : 'mc-offline',
        ]);

        ActionLog::log('settings.updated');

        $response = redirect()->route('admin.settings.index')->with('success', trans('admin.settings.status.updated'));

        if (setting('register', false) !== $request->has('register')) {
            $this->optimizer->reloadRoutesCache();
        }

        return $response;
    }

    /**
     * Show the application security settings.
     *
     * @return \Illuminate\Http\Response
     */
    public function security()
    {
        $show = (setting('recaptcha-site-key') && setting('recaptcha-secret-key')) || old('recaptcha');

        return view('admin.settings.security', [
            'hashAlgorithms' => $this->hashAlgorithms,
            'currentHash' => config('hashing.driver'),
            'showReCaptcha' => $show,
        ]);
    }

    /**
     * Update the application security settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function updateSecurity(Request $request)
    {
        $enableReCaptcha = $request->has('recaptcha');
        $hash = array_keys($this->hashAlgorithms);

        $settings = $this->validate($request, [
            'recaptcha-site-key' => ['required_with:recaptcha', 'max:50'],
            'recaptcha-secret-key' => ['required_with:recaptcha', 'max:50'],
            'hash' => [
                'required', 'string', Rule::in($hash), function ($attribute, $value, $fail) {
                    if (! array_key_exists($value, $this->hashCompatibility)) {
                        return;
                    }

                    if (! defined($this->hashCompatibility[$value])) {
                        $fail(trans('admin.settings.security.hash-error'));
                    }
                },
            ],
        ]);

        if ($enableReCaptcha) {
            Setting::updateSettings($settings);
        } else {
            Setting::updateSettings($request->only(['hash']));

            Setting::whereIn('name', ['recaptcha-site-key', 'recaptcha-secret-key'])->delete();
        }

        ActionLog::log('settings.updated');

        return redirect()->route('admin.settings.security')->with('success', trans('admin.settings.status.updated'));
    }

    public function performance()
    {
        return view('admin.settings.performance', ['cacheStatus' => $this->optimizer->isEnabled()]);
    }

    /**
     * Clear the application cache.
     *
     * @return \Illuminate\Http\Response
     */
    public function clearCache()
    {
        $response = redirect()->route('admin.settings.performance');

        if (! $this->cache->flush()) {
            return $response->with('error', trans('admin.settings.performances.cache.status.clear-error'));
        }

        return $response->with('success', trans('admin.settings.performances.cache.status.cleared'));
    }

    public function enableAdvancedCache()
    {
        $redirect = redirect()->route('admin.settings.performance');
        $cacheStatus = $this->optimizer->isEnabled();

        if (! $this->optimizer->cache()) {
            return $redirect->with('error', trans('admin.settings.performances.boost.status.enable-error'));
        }

        return $redirect->with('success',
            trans('admin.settings.performances.boost.status.'.($cacheStatus ? 'reloaded' : 'enabled')));
    }

    public function disableAdvancedCache()
    {
        $this->optimizer->clear();

        return redirect()->route('admin.settings.performance')
            ->with('success', trans('admin.settings.performances.boost.status.disabled'));
    }

    public function linkStorage()
    {
        $storagePublicPath = public_path('storage');

        File::delete($storagePublicPath);

        File::link(storage_path('app/public'), $storagePublicPath);

        return redirect()->route('admin.settings.performance')->with('success', 'Success');
    }

    public function seo()
    {
        return view('admin.settings.seo', [
            'enableAnalytics' => setting('g-analytics-id') || old('enable-g-analytics'),
            'htmlHead' => setting('html-head'),
            'htmlBody' => setting('html-body'),
        ]);
    }

    /**
     * Update the application SEO settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateSeo(Request $request)
    {
        Setting::updateSettings($this->validate($request, [
            'keywords' => ['nullable', 'string', 'max:150'],
            'g-analytics-id' => ['nullable', 'string', 'max:50'],
            'html-head' => ['nullable', 'string'],
            'html-body' => ['nullable', 'string'],
        ]));

        ActionLog::log('settings.updated');

        return redirect()->route('admin.settings.seo')->with('success', trans('admin.settings.status.updated'));
    }

    /**
     * Show the application mail settings.
     *
     * @return \Illuminate\Http\Response
     */
    public function mail()
    {
        return view('admin.settings.mail', [
            'mailers' => $this->mailMailers,
            'encryptionTypes' => $this->mailEncryptionTypes,
            'smtpConfig' => config('mail.mailers.smtp', optional([])),
        ]);
    }

    /**
     * Update the application mail settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateMail(Request $request)
    {
        $mailSettings = $this->validate($request, [
            'from-address' => ['required', 'string', 'email'],
            'mailer' => ['nullable', Rule::in(array_keys($this->mailMailers))],
            'smtp-encryption' => ['nullable', Rule::in(array_keys($this->mailEncryptionTypes))],
            'smtp-host' => ['required_if:driver,smtp', 'nullable', 'string'],
            'smtp-port' => ['required_if:driver,smtp', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp-username' => ['nullable', 'string'],
            'smtp-password' => ['nullable', 'string'],
        ]);

        $mailSettings['smtp-password'] = encrypt($mailSettings['smtp-password'], false);

        foreach ($mailSettings as $key => $value) {
            Setting::updateSettings('mail.'.str_replace('-', '.', $key), $value);
        }

        ActionLog::log('settings.updated');

        return redirect()->route('admin.settings.mail')->with('success', trans('admin.settings.status.updated'));
    }

    /**
     * Show the application maintenance settings.
     *
     * @return \Illuminate\Http\Response
     */
    public function maintenance()
    {
        return view('admin.settings.maintenance');
    }

    /**
     * Update the application maintenance settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateMaintenance(Request $request)
    {
        Setting::updateSettings($this->validate($request, [
            'maintenance-message' => ['nullable', 'string'],
        ]));

        Setting::updateSettings('maintenance-status', $request->has('maintenance-status'));

        return redirect()->route('admin.settings.maintenance')->with('success', trans('admin.settings.status.updated'));
    }

    protected function getAvailableLocales()
    {
        return $this->getAvailableLocaleCodes()->mapWithKeys(function ($file) {
            return [$file => trans('messages.lang', [], $file)];
        });
    }

    protected function getAvailableLocaleCodes()
    {
        return collect(File::directories($this->app->langPath()))->map(function ($path) {
            return basename($path);
        });
    }
}
