import React, {useLayoutEffect, useRef} from 'react';
import {useTranslate} from '@akeneo-pim-community/legacy-bridge';

interface newOptionPlaceholderProps {
  cancelNewOption: () => void;
}

const NewOptionPlaceholder = ({cancelNewOption}: newOptionPlaceholderProps) => {
    const translate = useTranslate();
    const placeholderRef = useRef<HTMLDivElement | null>(null);

    useLayoutEffect(() => {
        if (placeholderRef && placeholderRef.current) {
            placeholderRef.current.scrollIntoView();
        }
    }, []);

    return (
        <div className="AknAttributeOption-listItem AknAttributeOption-listItem--selected" role="new-option-placeholder" ref={placeholderRef}>
            <span className="AknAttributeOption-itemCode AknAttributeOption-itemCode--new">
                {translate('pim_enrich.entity.attribute_option.module.edit.new_option_code')}
            </span>
            <span className="AknAttributeOption-cancel-new-option-icon" onClick={() => cancelNewOption()} role="new-option-cancel"/>
        </div>
    );
};

export default NewOptionPlaceholder;
