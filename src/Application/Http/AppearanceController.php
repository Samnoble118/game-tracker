<?php

declare(strict_types=1);

/** Handles the dedicated authenticated appearance-customisation area. */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\DashboardCustomizer;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\User;
use InvalidArgumentException;

/** Updates themes, layout density, and dashboard artwork. */
final readonly class AppearanceController
{
    /** Creates the controller with appearance, security, and view dependencies. */
    public function __construct(private DashboardCustomizer $customizer, private CsrfToken $csrf, private string $templatePath) {}

    /** Processes appearance forms and renders the settings page. @param array<string,mixed> $server @param array<string,mixed> $query @param array<string,mixed> $input @param array<string,mixed> $files */
    public function handle(User $user, array $server, array $query, array $input, array $files=[]): void
    {
        $errors=[]; $section=(string)($input['section'] ?? 'theme');
        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->csrf->isValid(isset($input['_token']) ? (string)$input['_token'] : null)) $errors[]='Your session expired. Refresh and try again.';
            else {
                try {
                    if ($section === 'dashboard-artwork') {
                        if (($input['appearance_action'] ?? 'save') === 'remove') $this->customizer->remove($user);
                        else $this->customizer->update($user,(string)($input['image_mode'] ?? 'banner'),(int)($input['overlay'] ?? 55),isset($files['dashboard_image'])&&is_array($files['dashboard_image'])?$files['dashboard_image']:null);
                    } elseif ($section === 'merchandise-artwork') {
                        if (($input['appearance_action'] ?? 'save') === 'remove') $this->customizer->removeMerchandise($user);
                        else $this->customizer->updateMerchandise($user,(string)($input['image_mode'] ?? 'banner'),(int)($input['overlay'] ?? 55),isset($files['merchandise_image'])&&is_array($files['merchandise_image'])?$files['merchandise_image']:null);
                    } elseif ($section === 'franchise-artwork') {
                        if (($input['appearance_action'] ?? 'save') === 'remove') $this->customizer->removeFranchise($user);
                        else $this->customizer->updateFranchise($user,(string)($input['image_mode'] ?? 'banner'),(int)($input['overlay'] ?? 55),isset($files['franchise_image'])&&is_array($files['franchise_image'])?$files['franchise_image']:null);
                    } elseif (($input['theme_action'] ?? 'save') === 'reset') $this->customizer->resetTheme($user);
                    else $this->customizer->updateTheme($user,(string)($input['theme_preset'] ?? 'archive-purple'),(string)($input['theme_accent'] ?? ''),(string)($input['theme_background'] ?? ''),(string)($input['theme_panel'] ?? ''),(string)($input['theme_text'] ?? ''),(string)($input['layout_density'] ?? 'spacious'));
                } catch (InvalidArgumentException $exception) { $errors[]=$exception->getMessage(); }
                if ($errors===[]) { header('Location: /?route=appearance&saved='.$section,true,303); return; }
            }
        }
        $presets=DashboardCustomizer::PRESETS; $csrfToken=$this->csrf->value(); $saved=(string)($query['saved'] ?? '');
        require $this->templatePath;
    }
}
