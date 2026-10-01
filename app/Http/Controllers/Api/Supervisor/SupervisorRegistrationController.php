<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Supervisor\SupervisorRegistrationRequest;
use App\Services\Supervisor\ContractorSignupOptionsService;
use App\Services\Supervisor\SupervisorRegistrationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SupervisorRegistrationController extends Controller
{
    public function __construct(
        private readonly SupervisorRegistrationService $registration,
        private readonly ContractorSignupOptionsService $signupOptions
    ) {}

    /**
     * GET /api/contractor/auth/registration-options
     * Contractor (= supervisor) signup dropdowns only — not marketplace vendor.
     */
    public function registrationOptions(): JsonResponse
    {
        $data = $this->signupOptions->appRegistrationOptions();

        return ApiResponse::success('Registration options retrieved successfully.', $data)
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    public function register(SupervisorRegistrationRequest $request): JsonResponse
    {
        try {
            $row = $this->registration->register(
                $request->validated(),
                $request->documentFiles()
            );

            return ApiResponse::success(
                SupervisorRegistrationService::SUCCESS_MESSAGE,
                $row->toApiArray(),
                201
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            $sql = $e->getMessage();
            if (str_contains($sql, 'phone') && str_contains($sql, 'Duplicate')) {
                $msg = 'This phone number is already registered.';

                return ApiResponse::error($msg, 422, ['phone' => [$msg]]);
            }
            if (str_contains($sql, 'email') && str_contains($sql, 'Duplicate')) {
                $msg = 'This email is already registered.';

                return ApiResponse::error($msg, 422, ['email' => [$msg]]);
            }
            Log::error('Supervisor registration DB error: '.$e->getMessage());

            return ApiResponse::error('Registration could not be saved. Please try again.', 500);
        } catch (\Throwable $e) {
            Log::error('Supervisor registration failed: '.$e->getMessage());

            return ApiResponse::error('Registration failed. Please check your details and try again.', 500);
        }
    }
}
