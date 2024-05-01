(($, Drupal, drupalSettings, debounce, dragula, once) => {

  function getContext(el) {
    return {
      parent_type: el.closest('[data-me-component-type]')
        ? el.closest('[data-me-component-type]').getAttribute('data-me-component-type')
        : null,
      region: el.getAttribute('data-region') || '_root',
      layout: el.closest('[data-layout]')
        ? el.closest('[data-layout]').getAttribute('data-layout')
        : null,
      sibling_type: el.previousElementSibling
        ? el.previousElementSibling.getAttribute('data-me-component-type')
        : null,
    };
  }

  function applicableRestrictions(restrictions, targetContext) {
    // Filters the list of restrictions to only those that apply to the current context.
    return restrictions.filter((restriction) => {
      for (let key in restriction.context) {
        const value = restriction.context[key];
        if (value.indexOf('!') === 0) {
          if (targetContext[key] && targetContext[key] !== value.substring(1)) {
            return true;
          }
        }
        else {
          if (targetContext[key] && targetContext[key] === value) {
            return true;
          }
        }
      }
      return false;
    });
  }

  $(document).on('lpb-component:move lpb-component:drop', (e, uuid) => {
    setTimeout(() => {
      const el = document.querySelector(`[data-me-transform]`);
      if (!el) {
        return;
      }
      const layoutId = el.closest('[data-lpb-id]').getAttribute('data-lpb-id');
      const transform = el.getAttribute('data-me-transform');
      if (!transform) {
        return;
      }
      Drupal.ajax({
        url: `${drupalSettings.path.baseUrl}${drupalSettings.path.pathPrefix}mercury-editor-restrictions/transform/${layoutId}/${uuid}/${transform}`,
      }).execute();

    }, 100);
  });

  Drupal.behaviors.layoutParagraphsRestrictions = {
    attach: function attach(context, settings) {
      $(once('lpb-restrictions', '[data-lpb-id]'))
        .each((i, e) => {
          const lpbId = e.getAttribute('data-lpb-id');
          if (
            typeof settings.lpBuilder.restrictions !== 'undefined' &&
            typeof settings.lpBuilder.restrictions[lpbId] !== 'undefined'
            ) {
            const allRestrictions = settings.lpBuilder.restrictions[lpbId];
            Drupal.registerLpbMoveError((settings, el, target, source, sibling) => {
              // The context that element is being moved into.
              const moveContext = getContext(target);

              // Filters the list of restrictions to only those that apply to the current context.
              const appliedRestrictions = applicableRestrictions(allRestrictions, moveContext);

              // Build a list of transformations that apply.
              const type = el.getAttribute('data-me-component-type') || [];
              // Build an exclude list from restrictions that apply.
              const exclude = appliedRestrictions.reduce((exclude, restriction) => {
                return [...exclude, ...restriction.exclude_components || []];
              }, []);

              // Build an exclude list from restrictions that apply.
              const transformations = appliedRestrictions.reduce((transformations, restriction) => {
                if (restriction.transform) {
                  for (let src in restriction.transform) {
                    const transformTo = restriction.transform[src];
                    // If the transformation is not in the exclude list, add it.
                    if (exclude.indexOf(transformTo) == -1) {
                      if (src.indexOf('*')) {
                        if (type.indexOf(src.replace('*', '')) === 0) {
                          return [...transformations, transformTo];
                        }
                      }
                      else {
                        if (type === src) {
                          return [...transformations, transformTo];
                        }
                      }
                    }
                  }
                }
                return transformations;
              }, []);

              // Build an include list from restrictions that apply.
              const include = appliedRestrictions.reduce((include, restriction) => {
                return [...include, ...restriction.components || []];
              }, []);
              if (transformations.length > 0) {
                el.setAttribute('data-me-transform', transformations[0]);
                return;
              }

              // Compare the type of the element to the include/exclude lists.
              if (exclude.length > 0 && exclude.indexOf(type) !== -1) {
                return 'This component cannot be moved here.';
              }
              if (include.length > 0 && include.indexOf(type) === -1) {
                return 'This component cannot be moved here.';
              }
            });
          }
        });
    }
  }

})(jQuery, Drupal, drupalSettings, Drupal.debounce, dragula, once);
