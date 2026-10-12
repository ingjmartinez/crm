<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeploymentAssetsTest extends TestCase
{
    public function test_vite_assets_have_one_build_source_and_a_shared_volume(): void
    {
        $root = dirname(__DIR__, 2);
        $appDockerfile = file_get_contents($root.'/dockerfile');
        $nginxDockerfile = file_get_contents($root.'/docker/nginx/Dockerfile');
        $compose = file_get_contents($root.'/docker-compose.yml');
        $entrypoint = file_get_contents($root.'/docker/entrypoint.sh');
        $dockerignore = file_get_contents($root.'/.dockerignore');

        $this->assertStringContainsString('COPY --from=node-build /app/public/build /opt/crm-build', $appDockerfile);
        $this->assertStringContainsString('ENTRYPOINT ["/bin/sh", "/usr/local/bin/crm-entrypoint"]', $appDockerfile);
        $this->assertStringNotContainsString('COPY --from=node-build /app/public/build /var/www/public/build', $appDockerfile);
        $this->assertStringNotContainsString('npm run build', $nginxDockerfile);
        $this->assertStringNotContainsString('public/build /var/www/public/build', $nginxDockerfile);
        $this->assertStringContainsString('crm_build:/var/www/public/build', $compose);
        $this->assertStringContainsString('crm_build:/var/www/public/build:ro', $compose);
        $this->assertStringContainsString('cp /opt/crm-build/manifest.json /var/www/public/build/.manifest.json.tmp', $entrypoint);
        $this->assertStringContainsString('mv -f /var/www/public/build/.manifest.json.tmp /var/www/public/build/manifest.json', $entrypoint);
        $this->assertStringContainsString('public/build', $dockerignore);
        $this->assertStringContainsString('public/hot', $dockerignore);
    }
}
