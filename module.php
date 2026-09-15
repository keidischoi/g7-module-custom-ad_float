<?php

namespace Modules\Custom\AdFloat;

use App\Extension\AbstractModule;
use Modules\Custom\AdFloat\Listeners\InjectAdFloatListener;

class Module extends AbstractModule
{
    public function getRoles(): array
    {
        return [];
    }

    public function getPermissions(): array
    {
        return [
            'name' => [
                'ko' => '플로팅 광고',
                'en' => 'Floating Ads',
            ],
            'description' => [
                'ko' => '플로팅 광고를 관리합니다.',
                'en' => 'Manage floating advertisements.',
            ],
            'categories' => [
                [
                    'identifier' => 'ads',
                    'resource_route_key' => 'ad-float',
                    'owner_key' => null,
                    'name' => [
                        'ko' => '플로팅 광고 관리',
                        'en' => 'Floating Ad Management',
                    ],
                    'description' => [
                        'ko' => '광고 설정 및 이미지를 관리합니다.',
                        'en' => 'Manage floating ad settings and images.',
                    ],
                    'permissions' => [
                        [
                            'action' => 'read',
                            'name' => ['ko' => '조회', 'en' => 'View'],
                            'description' => ['ko' => '광고 관리 화면 조회', 'en' => 'View ad management'],
                            'type' => 'admin',
                            'roles' => ['admin', 'manager'],
                        ],
                        [
                            'action' => 'create',
                            'name' => ['ko' => '등록', 'en' => 'Create'],
                            'description' => ['ko' => '광고 이미지 등록', 'en' => 'Upload ads'],
                            'type' => 'admin',
                            'roles' => ['admin', 'manager'],
                        ],
                        [
                            'action' => 'update',
                            'name' => ['ko' => '수정', 'en' => 'Update'],
                            'description' => ['ko' => '광고 설정 및 상태 수정', 'en' => 'Update settings and status'],
                            'type' => 'admin',
                            'roles' => ['admin', 'manager'],
                        ],
                        [
                            'action' => 'delete',
                            'name' => ['ko' => '삭제', 'en' => 'Delete'],
                            'description' => ['ko' => '광고 이미지 삭제', 'en' => 'Delete ads'],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getAdminMenus(): array
    {
        return [
            [
                'name' => [
                    'ko' => '플로팅 광고',
                    'en' => 'Floating Ads',
                ],
                'slug' => 'custom-ad_float',
                'url' => '/admin/ad-float',
                'icon' => 'fas fa-images',
                'order' => 90,
                'permission' => 'custom-ad_float.ads.read',
            ],
        ];
    }

    public function getRoutes(): array
    {
        $api = $this->getModulePath().'/src/routes/api.php';
        $routes = [];
        if (is_file($api)) {
            $routes['api'] = $api;
        }

        return $routes;
    }

    public function getHookListeners(): array
    {
        return [
            InjectAdFloatListener::class,
        ];
    }
}
