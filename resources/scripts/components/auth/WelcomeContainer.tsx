import React from 'react';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button';
import WelcomeFormContainer from '@/components/auth/WelcomeFormContainer';
import { Link } from 'react-router-dom';

export default () => (
    <WelcomeFormContainer css={tw`w-full flex`} title={'Welcome to CXMPUTE'}>
        <div className={'grid grid-cols-5'}>
            <Link to={'/auth/login'} className={'col-span-2'}>
                <Button size={Button.Sizes.Large} className={'w-full'}>
                    LOGIN
                </Button>
            </Link>
            <h4 className={'text-xl text-center my-2 text-gray-800'}>OR</h4>
            <Link to={'/auth/signup'} className={'col-span-2'}>
                <Button size={Button.Sizes.Large} className={'w-full'}>
                    SIGN UP
                </Button>
            </Link>
        </div>
    </WelcomeFormContainer>
);
