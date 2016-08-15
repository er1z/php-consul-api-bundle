<?php namespace DCarbone\PHPConsulAPIBundle\DependencyInjection;

/*
   Copyright 2016 Daniel Carbone (daniel.p.carbone@gmail.com)

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class PHPConsulAPIExtension
 * @package DCarbone\PHPConsulAPIBundle\DependencyInjection
 */
class PHPConsulAPIExtension extends Extension
{
    /**
     * @return string
     */
    public function getAlias()
    {
        return 'consul_api';
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     * @return Configuration
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration();
    }

    /**
     * Loads a specific configuration.
     *
     * @param array $configs An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('consul_api.yml');

        $bundles = $container->getParameter('kernel.bundles');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $configNames = array();

        foreach($config as $name => $conf)
        {
            $configNames[] = $name;
            $this->_addServiceDefinition($name, $conf, $container);
        }

        $container->setParameter('consul_api.config_names', $configNames);

        // Load twig extension if twig is loaded
        if (isset($bundles['TwigBundle']))
        {
            $service = new Definition(
                \DCarbone\PHPConsulAPIBundle\Twig\PHPConsulAPIExtension::class,
                array(new Parameter('consul_api.config_names'))
            );
            $service->addMethodCall('addConsul', array('default', new Reference('consul_api.default')));

            foreach($configNames as $configName)
            {
                $service->addMethodCall(
                    'addConsul',
                    array($configName, new Reference(sprintf('consul_api.%s', $configName)))
                );
            }

            $service->addTag('twig.extension');

            $container->setDefinition('consul_api.twig.extension', $service);
        }
    }

    /**
     * @param string $name
     * @param array $conf
     * @param ContainerBuilder $container
     */
    private function _addServiceDefinition($name, array $conf, ContainerBuilder $container)
    {
        $serviceName = sprintf('consul_api.%s', $name);
        $configName = sprintf('%s.config', $serviceName);

        $container->setDefinition(
            $configName,
            $this->_newConfigDefinition($conf)
        );

        $service = $container->setDefinition(
            $serviceName,
            new DefinitionDecorator('consul_api.default')
        );

        $service->setArguments([new Reference($configName)]);
    }

    /**
     * @param array $conf
     * @return Definition
     */
    private function _newConfigDefinition(array $conf)
    {
        static $mapping = array(
            'addr' => 'Address',
            'scheme' => 'Scheme',
            'datacenter' => 'Datacenter',
            'wait_time' => 'WaitTime',
            'http_auth' => 'HttpAuth',
            'token' => 'Token',
            'ca_file' => 'CAFile',
            'cert_file' => 'CertFile',
            'key_file' => 'KeyFile',
            'insecure_skip_verify' => 'InsecureSkipVerify',
            'curl_opts' => 'AdditionalCurlOpts',
        );

        $args = array();
        foreach($conf as $k => $v)
        {
            if (isset($mapping[$k]))
                $args[$mapping[$k]] = $v;
        }

        return new Definition('DCarbone\\PHPConsulAPI\\Config', [$args]);
    }
}