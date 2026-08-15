<?php

namespace App\Services;

use App\Entity\User;
use App\Repositories\InstitutionProfileRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;

class ValidationSessionService
{
    private const  CONTINUE_NEXT_VALIDATION = 1;
    private const  BREAK_CHAIN_VALIDATION_AND_RENDER = 0;

    public static function verifyPhoneConfirmation($urlViews): bool
    {

        $user = LoginService::getSession();

        if ($user->getLevel() === User::$ADMIN_USER_LEVEL) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        if ($user->getPhoneValidation() == 1) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        if (
            str_contains(implode("/",$urlViews), "phone/validation") ||
            str_contains(implode("/",$urlViews), "phone/code")
        ) {
            return self::BREAK_CHAIN_VALIDATION_AND_RENDER;
        }


        LocationUtils::redirectInternal("panel/phone/validation");
    }

    public static function verifyInstitutionProfile($urlViews): bool
    {
        $user = LoginService::getSession();

        if (!in_array($user->getLevel(), [2, 3])) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        if (!in_array("planner-hub", $urlViews)) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        if (in_array("institution-profile", $urlViews)) {
            return self::BREAK_CHAIN_VALIDATION_AND_RENDER;
        }

        $institutionRepo = new InstitutionProfileRepository();
        $institutionProfile = $institutionRepo->getByOwner($user->getOwner());

        if ($institutionProfile && ((int)$user->getLevel() !== 2 || $institutionRepo->isProfileComplete($institutionProfile))) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        $missingItems = $institutionProfile ? $institutionRepo->getMissingProfileItems($institutionProfile) : ['Business Profile'];
        $missing = implode(", ", BusinessProfileBuilderService::translatedMissingItems($missingItems));
        MessageUtil::setMessage(TranslationService::trans('business_profile_builder.alerts.complete_before_continue_missing', ['missing' => $missing]));
        LocationUtils::redirectInternal("panel/planner-hub/institution-profile");
    }

    public static function verifyMembership($urlViews): bool
    {
        $user = LoginService::getSession();

        if (!in_array($user->getLevel(), [2, 3])) {
            return self::CONTINUE_NEXT_VALIDATION;
        }


        if (!in_array("planner-hub", $urlViews)) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        if ($user->hasActiveMembership()) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        if ((new ModuleAccessService())->userHasActiveBaseMembership($user)) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        MessageUtil::setMessage("🔒 You need an active membership to access this feature.");
        LocationUtils::redirectInternal("panel/membership/pay");
    }

    public static function verifyUserPermissions($urlViews): bool
    {
        $user = LoginService::getSession();

        if ($user->getLevel() != 4) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        if ( count($urlViews)  < 4) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        if ($urlViews[1] != "planner-hub" || $urlViews[2] != "management") {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        if ($user->hasPermissionForModule($urlViews[3])) {
            return self::CONTINUE_NEXT_VALIDATION;
        }

        if (str_contains(implode("/",$urlViews), "planner-hub/management")) {
            return self::BREAK_CHAIN_VALIDATION_AND_RENDER;
        }

        MessageUtil::setMessage("This area requires admin approval for the selected company.");
        LocationUtils::redirectInternal("panel/planner-hub/no-access?module={$urlViews[3]}");
    }


    public static function verifyTokenView($urlViews, $protectedUrlPrefix)
    {
        // Access Token for mobile application
        if (
            count($urlViews) > 1 &&
            $urlViews[0] === $protectedUrlPrefix &&
            $urlViews[1] === "tokenapi"
        ) {

            // internal path after token
            $internalRoute = array_slice($urlViews, 3);

            $token = $urlViews[2] ?? null;
            $user = LoginService::validateToken($token);

            if (!$token || count($internalRoute) == 0 || $user == null) {
                LocationUtils::redirectInternal("login");
            }

            LoginService::setSession($user);
            $_SESSION['IS_MOBILE_APP'] = true;
            $redirectTo = implode("/", $internalRoute);
            LocationUtils::redirectInternal($redirectTo);
        }
    }
}
