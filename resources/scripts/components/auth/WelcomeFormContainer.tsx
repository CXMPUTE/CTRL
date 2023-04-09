import React from 'react';
import tw from 'twin.macro';
import { breakpoint } from '@/theme';
import styled from 'styled-components/macro';
import FlashMessageRender from '@/components/FlashMessageRender';

interface Props {
    title: string;
    children: React.ReactChild[] | React.ReactChild;
}

const Container = styled.div`
    ${breakpoint('sm')`
        ${tw`w-4/5 mx-auto`}
    `};

    ${breakpoint('md')`
        ${tw`p-10`}
    `};

    ${breakpoint('lg')`
        ${tw`w-3/5`}
    `};

    ${breakpoint('xl')`
        ${tw`w-full`}
        max-width: 700px;
    `};
`;

export default ({ title, children }: Props) => (
    <Container>
        {title && <h2 css={tw`text-3xl text-center text-neutral-100 font-medium py-4`}>{title}</h2>}
        <FlashMessageRender css={tw`mb-2 px-1`} />
        <div css={tw`md:flex w-full bg-white shadow-lg rounded-lg p-6 mx-1`}>
            <div css={tw`lg:grid lg:col-span-2 w-full`}>
                <img src={'/assets/svgs/logo.png'} css={tw`block w-48 md:w-64 mx-auto`} />
                <div css={tw`flex-1`}>{children}</div>
            </div>
        </div>
        <p css={tw`text-center text-neutral-500 text-xs mt-4`}>
            &copy; CXMPUTE, built on <a href={'https://pterodactyl.io'}>Pterodactyl</a>.
        </p>
    </Container>
);
