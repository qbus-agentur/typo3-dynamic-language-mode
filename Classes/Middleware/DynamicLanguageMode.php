<?php
declare(strict_types = 1);
namespace Qbus\DynamicLanguageMode\Middleware;

use Doctrine\DBAL\FetchMode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class DynamicLanguageMode implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $lang = $this->getCurrentSiteLanguage($request);
        $pageArguments = $this->getPageArguments($request);

        if ($lang === null || $pageArguments === null) {
            return $handler->handle($request);
        }

        $site = $request->getAttribute('site');
        $default = $site->getLanguages()[0];
        if ($lang->getFallbackType() !== 'free' && $lang->getLanguageId() !== $default->getLanguageId()) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
            $queryBuilder->getConcreteQueryBuilder()->select(
                '(l18n_parent != 0) AS dlm_is_connected',
                "count('*') AS count"
            );
            $query = $queryBuilder
                ->from('tt_content', 't')
                ->andWhere($queryBuilder->expr()->eq('t.sys_language_uid', $queryBuilder->createNamedParameter(intval($lang->getLanguageId()), \PDO::PARAM_INT)))
                ->andWhere($queryBuilder->expr()->eq('t.pid', $queryBuilder->createNamedParameter(intval($pageArguments->getPageId()), \PDO::PARAM_INT)))
                ->groupBy('dlm_is_connected');
            $stmt = $query->execute();

            $translationStatus = [];
            foreach ($stmt->fetchAll(FetchMode::NUMERIC) as [$key, $value]) {
                $translationStatus[(int)$key] = $value;
            }
            $countUnconnectedElements = $translationStatus[0] ?? 0;
            $countConnectedElements = $translationStatus[1] ?? 0;

            $enableFallbackTypeFree = false;
            if ($lang->getFallbackType() === 'strict') {
                if ($countConnectedElements > 0 && $countUnconnectedElements > 0) {
                    // Set fallbackType: free when page is in mixed-mode,
                    // as fallbackType: strict will
                    $enableFallbackTypeFree = true;
                } else {
                    // Stay with fallbackType: strict when page is in Free or Connected mode
                    // as `fallbackType: strict` will be pretty much like `fallbackType: free`
                    // in Free mode, but relations (e.g sys_file_metadata) will one be properly
                    // translated with `fallbackType: strict`.
                    $enableFallbackTypeFree = false;
                }
            } elseif ($countUnconnectedElements > 0) {
                // Page is in "Free mode" (or mixed mode), apply `fallbackType: free`
                $enableFallbackTypeFree = true;
            }

            $countPage = 0;
            if ($enableFallbackTypeFree) {
                $queryBuilderPage = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
                $queryPage = $queryBuilderPage
                    ->count('*')
                    ->from('pages', 'p')
                    ->where($queryBuilder->expr()->eq('p.l10n_parent', $queryBuilderPage->createNamedParameter(intval($pageArguments->getPageId()), \PDO::PARAM_INT)))
                    ->andWhere($queryBuilder->expr()->eq('p.sys_language_uid', $queryBuilderPage->createNamedParameter(intval($lang->getLanguageId()), \PDO::PARAM_INT)));
                $countPage = $queryPage->execute()->fetchColumn();
            }
            if ($enableFallbackTypeFree && $countPage > 0) {
                \Closure::bind(function() use ($lang, $newId) {
                    $lang->fallbackType = 'free';
                    $lang->fallbackLanguageIds = [];
                }, null, SiteLanguage::class)();
            }
        }

        return $handler->handle($request);
    }

    protected function getCurrentSiteLanguage(ServerRequestInterface $request): ?SiteLanguage
    {
        if ($request->getAttribute('language') instanceof SiteLanguage) {
            return $request->getAttribute('language');
        }
        return null;
    }

    protected function getPageArguments(ServerRequestInterface $request): ?PageArguments
    {
        if ($request->getAttribute('routing') instanceof PageArguments) {
            return $request->getAttribute('routing');
        }
        return null;
    }
}
