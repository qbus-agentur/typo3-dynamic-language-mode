<?php
declare(strict_types = 1);
namespace Qbus\DynamicLanguageMode\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class LanguageRedirect implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, PsrRequestHandlerInterface $handler): ResponseInterface
    {
        $lang = $this->getCurrentSiteLanguage($request);
        $pageArguments = $this->getPageArguments($request);

        if ($lang === null || $pageArguments === null) {
            return $handler->handle($request);
        }

        $site = $request->getAttribute('site');
        $default = $site->getLanguages()[0];
        $icvPageBaseLanguage = (int)($GLOBALS['TSFE']->page['tx_icv_language_uid'] ?? 1) ?: 1;

	//\TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($lang);
	//\TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($default);

	if ($lang->languageId !== $default->languageId) {
            // Check if page is in "Free mode" and apply a dynamic language configuration in that case
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
            $query = $queryBuilder
                ->count('*')
                ->from('tt_content', 't')
                ->where('l18n_parent = 0')
                ->andWhere($queryBuilder->expr()->eq('t.sys_language_uid', $queryBuilder->createNamedParameter(intval($lang->getLanguageId()), \PDO::PARAM_INT)))
                ->andWhere($queryBuilder->expr()->eq('t.pid', $queryBuilder->createNamedParameter(intval($pageArguments->getPageId()), \PDO::PARAM_INT)));
            //$sql = $query->getSql();
            //var_dump($sql);
            $count = $query->execute()->fetchColumn();

            if ($count > 0) {
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
